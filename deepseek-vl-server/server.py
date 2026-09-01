"""
DeepSeek-VL inference server for Aura AI chart scanner.

This is the open-source Vision-Language model from:
  https://github.com/deepseek-ai/DeepSeek-VL

It is NOT the cloud api.deepseek.com chat API (text-only).
Run this on a GPU host, then set on the Aura API:

  DEEPSEEK_VL_URL=http://your-gpu-host:8787
  DEEPSEEK_API_KEY=optional-shared-secret   # same value as VL_API_KEY below

Quick start (GPU recommended):
  cd deepseek-vl-server
  python -m venv .venv && source .venv/bin/activate
  pip install -r requirements.txt
  pip install -e "git+https://github.com/deepseek-ai/DeepSeek-VL.git#egg=deepseek_vl"
  uvicorn server:app --host 0.0.0.0 --port 8787
"""

from __future__ import annotations

import base64
import io
import os
import re
import tempfile
from typing import Any

from fastapi import FastAPI, Header, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel, Field
from PIL import Image

# ---------------------------------------------------------------------------
# Config
# ---------------------------------------------------------------------------

MODEL_PATH = os.environ.get("DEEPSEEK_VL_MODEL", "deepseek-ai/deepseek-vl-1.3b-chat")
DEVICE = os.environ.get("DEEPSEEK_VL_DEVICE", "cuda")  # cuda | cpu | mps
MAX_NEW_TOKENS = int(os.environ.get("DEEPSEEK_VL_MAX_TOKENS", "1024"))
VL_API_KEY = os.environ.get("VL_API_KEY", "").strip()  # optional bearer
PORT = int(os.environ.get("PORT", "8787"))

app = FastAPI(title="DeepSeek-VL Chart Server", version="1.0.0")
app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],
    allow_methods=["*"],
    allow_headers=["*"],
)

_vl_processor = None
_vl_tokenizer = None
_vl_model = None


def _require_auth(authorization: str | None) -> None:
    if not VL_API_KEY:
        return
    if not authorization or not authorization.startswith("Bearer "):
        raise HTTPException(status_code=401, detail="Missing Bearer token")
    token = authorization.removeprefix("Bearer ").strip()
    if token != VL_API_KEY:
        raise HTTPException(status_code=401, detail="Invalid token")


def load_model() -> None:
    global _vl_processor, _vl_tokenizer, _vl_model
    if _vl_model is not None:
        return

    import torch
    from transformers import AutoModelForCausalLM
    from deepseek_vl.models import MultiModalityCausalLM, VLChatProcessor

    print(f"[DeepSeek-VL] Loading {MODEL_PATH} on {DEVICE}…")
    _vl_processor = VLChatProcessor.from_pretrained(MODEL_PATH)
    _vl_tokenizer = _vl_processor.tokenizer
    _vl_model = AutoModelForCausalLM.from_pretrained(
        MODEL_PATH,
        trust_remote_code=True,
    )

    if DEVICE == "cuda":
        _vl_model = _vl_model.to(torch.bfloat16).cuda().eval()
    elif DEVICE == "mps":
        _vl_model = _vl_model.to(torch.float16).to("mps").eval()
    else:
        _vl_model = _vl_model.to(torch.float32).cpu().eval()
        print("[DeepSeek-VL] WARNING: CPU mode is slow — use a GPU host for production.")

    print("[DeepSeek-VL] Ready.")


@app.on_event("startup")
def _startup() -> None:
    # Lazy-load on first request if SKIP_EAGER_LOAD=1 (faster container boot)
    if os.environ.get("SKIP_EAGER_LOAD") == "1":
        return
    try:
        load_model()
    except Exception as e:
        print(f"[DeepSeek-VL] Startup load failed (will retry on request): {e}")


def _decode_image(data_url_or_b64: str) -> Image.Image:
    raw = data_url_or_b64.strip()
    if raw.startswith("data:"):
        # data:image/jpeg;base64,....
        raw = raw.split(",", 1)[1]
    raw = re.sub(r"\s+", "", raw)
    try:
        blob = base64.b64decode(raw, validate=False)
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid base64 image: {e}") from e
    try:
        return Image.open(io.BytesIO(blob)).convert("RGB")
    except Exception as e:
        raise HTTPException(status_code=400, detail=f"Invalid image bytes: {e}") from e


def _generate(prompt: str, image: Image.Image) -> str:
    import torch
    from deepseek_vl.utils.io import load_pil_images

    load_model()
    assert _vl_processor is not None and _vl_tokenizer is not None and _vl_model is not None

    # DeepSeek-VL conversation format (see github.com/deepseek-ai/DeepSeek-VL)
    with tempfile.NamedTemporaryFile(suffix=".png", delete=True) as tmp:
        image.save(tmp.name, format="PNG")
        conversation = [
            {
                "role": "User",
                "content": f"<image_placeholder>{prompt}",
                "images": [tmp.name],
            },
            {"role": "Assistant", "content": ""},
        ]
        pil_images = load_pil_images(conversation)
        prepare_inputs = _vl_processor(
            conversations=conversation,
            images=pil_images,
            force_batchify=True,
        ).to(_vl_model.device)

        inputs_embeds = _vl_model.prepare_inputs_embeds(**prepare_inputs)
        outputs = _vl_model.language_model.generate(
            inputs_embeds=inputs_embeds,
            attention_mask=prepare_inputs.attention_mask,
            pad_token_id=_vl_tokenizer.eos_token_id,
            bos_token_id=_vl_tokenizer.bos_token_id,
            eos_token_id=_vl_tokenizer.eos_token_id,
            max_new_tokens=MAX_NEW_TOKENS,
            do_sample=False,
            use_cache=True,
        )
        answer = _vl_tokenizer.decode(outputs[0].cpu().tolist(), skip_special_tokens=True)
        # Strip echoed prompt / sft format if present
        if "Assistant:" in answer:
            answer = answer.split("Assistant:")[-1].strip()
        return answer.strip()


# ---------------------------------------------------------------------------
# Routes
# ---------------------------------------------------------------------------


class AnalyzeBody(BaseModel):
    image: str = Field(..., description="Base64 or data-URL image")
    mimeType: str = "image/jpeg"
    prompt: str = Field(..., description="Analysis prompt")


@app.get("/health")
def health() -> dict[str, Any]:
    return {
        "ok": True,
        "model": MODEL_PATH,
        "device": DEVICE,
        "loaded": _vl_model is not None,
        "engine": "deepseek-vl",
        "repo": "https://github.com/deepseek-ai/DeepSeek-VL",
    }


@app.post("/analyze")
def analyze(body: AnalyzeBody, authorization: str | None = Header(default=None)) -> dict[str, Any]:
    _require_auth(authorization)
    img = _decode_image(body.image)
    text = _generate(body.prompt, img)
    return {"message": "accept", "text": text, "model": MODEL_PATH}


class ChatCompletionsBody(BaseModel):
    model: str | None = None
    messages: list[dict[str, Any]]
    max_tokens: int | None = None
    temperature: float | None = None


@app.post("/v1/chat/completions")
def chat_completions(
    body: ChatCompletionsBody,
    authorization: str | None = Header(default=None),
) -> dict[str, Any]:
    """
    OpenAI-compatible shim so Aura's deepseekAnalyzeChartImage() works against DeepSeek-VL.
    Expects last user message content as array with text + image_url parts.
    """
    _require_auth(authorization)

    prompt = ""
    image_b64: str | None = None
    for msg in body.messages:
        if msg.get("role") != "user":
            continue
        content = msg.get("content")
        if isinstance(content, str):
            prompt = content
        elif isinstance(content, list):
            for part in content:
                if not isinstance(part, dict):
                    continue
                if part.get("type") == "text":
                    prompt = str(part.get("text") or prompt)
                if part.get("type") == "image_url":
                    url = (part.get("image_url") or {}).get("url") or ""
                    if url:
                        image_b64 = url

    if not image_b64:
        raise HTTPException(status_code=400, detail="image_url content part required for DeepSeek-VL")
    if not prompt:
        prompt = "Analyze this trading chart and return JSON only."

    img = _decode_image(image_b64)
    text = _generate(prompt, img)

    return {
        "id": "deepseek-vl-chart",
        "object": "chat.completion",
        "model": MODEL_PATH,
        "choices": [
            {
                "index": 0,
                "message": {"role": "assistant", "content": text},
                "finish_reason": "stop",
            }
        ],
    }


if __name__ == "__main__":
    import uvicorn

    uvicorn.run("server:app", host="0.0.0.0", port=PORT, reload=False)
