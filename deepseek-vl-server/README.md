# DeepSeek-VL chart vision server (self-hosted)
#
# Source model: https://github.com/deepseek-ai/DeepSeek-VL
# This is separate from the cloud key at api.deepseek.com (text-only).

## 1. GPU host (recommended)

```bash
cd deepseek-vl-server
python3 -m venv .venv && source .venv/bin/activate
pip install -r requirements.txt
pip install -e "git+https://github.com/deepseek-ai/DeepSeek-VL.git#egg=deepseek_vl"

# 1.3B is lighter; use 7B for better chart OCR
export DEEPSEEK_VL_MODEL=deepseek-ai/deepseek-vl-1.3b-chat
# export DEEPSEEK_VL_MODEL=deepseek-ai/deepseek-vl-7b-chat
export DEEPSEEK_VL_DEVICE=cuda
export VL_API_KEY=change-me   # optional shared secret

uvicorn server:app --host 0.0.0.0 --port 8787
```

Health: `curl http://127.0.0.1:8787/health`

## 2. Point Aura API at it

On Render / VPS env for `nextrade-ai`:

```
DEEPSEEK_VL_URL=http://YOUR_GPU_HOST:8787
DEEPSEEK_API_KEY=change-me          # must match VL_API_KEY if set
# Keep cloud DeepSeek for lot sizing (text):
# DEEPSEEK_API_BASE=https://api.deepseek.com
```

Chart scanner / no-signal warmup then call **DeepSeek-VL** for the image.
Lot sizing still uses the cloud DeepSeek text API when configured.

## 3. Models

| Model | Notes |
|-------|--------|
| `deepseek-ai/deepseek-vl-1.3b-chat` | Default — fits smaller GPUs |
| `deepseek-ai/deepseek-vl-7b-chat` | Better quality — needs ~16GB+ VRAM |

See Hugging Face links in the [DeepSeek-VL README](https://github.com/deepseek-ai/DeepSeek-VL).
