#!/usr/bin/env python3
"""
NexTradeAI MT5 copy-trade bridge.

Watches MetaTrader 5 open positions and publishes them to the NexTradeAI
`signals` table using your automation secret code (EA → Secret code in admin).

Requirements:
  pip install -r scripts/requirements-mt5.txt

Environment:
  NEXTRADE_EA_CODE       — secret code from admin/home/EA.php (required)
  NEXTRADE_API_BASE      — default https://www.nextradeai.io/api
  NEXTRADE_POLL_SECONDS  — poll interval (default 2)
  MT5_LOGIN              — optional MT5 account login
  MT5_PASSWORD           — optional MT5 password
  MT5_SERVER             — optional broker server name
  MT5_PATH               — optional terminal path (Windows)

Example:
  export NEXTRADE_EA_CODE="abc123..."
  python3 scripts/mt5-copy-trades.py
"""

from __future__ import annotations

import logging
import os
import sys
import time
from dataclasses import dataclass
from typing import Dict, Optional, Set

import requests

try:
    import MetaTrader5 as mt5
except ImportError:
    print("Install dependencies: pip install -r scripts/requirements-mt5.txt", file=sys.stderr)
    raise

LOG = logging.getLogger("nextrade.mt5")

API_BASE = os.getenv("NEXTRADE_API_BASE", "https://www.nextradeai.io/api").rstrip("/")
EA_CODE = os.getenv("NEXTRADE_EA_CODE", "").strip()
POLL_SECONDS = float(os.getenv("NEXTRADE_POLL_SECONDS", "2"))
THROTTLE_SECONDS = float(os.getenv("NEXTRADE_THROTTLE_SECONDS", "2"))


@dataclass
class TrackedPosition:
    identifier: int
    asset: str
    action: str


class NexTradeCopyBridge:
    def __init__(self, ea_code: str, api_base: str) -> None:
        self.ea_code = ea_code
        self.api_base = api_base.rstrip("/")
        self.session = requests.Session()
        self.session.headers.update({"Accept": "application/json", "Content-Type": "application/json"})
        self.published_ids: Set[int] = set()
        self.tracked: Dict[int, TrackedPosition] = {}
        self.is_martingale = False
        self.ea_name = ""
        self.last_publish_at = 0.0

    def authenticate(self) -> None:
        url = f"{self.api_base}/ea-auth"
        resp = self.session.get(url, params={"key": self.ea_code}, timeout=20)
        LOG.info("Auth HTTP %s — %s", resp.status_code, resp.text[:300])
        if resp.status_code != 200:
            raise RuntimeError(f"EA auth failed (HTTP {resp.status_code}): {resp.text}")
        data = resp.json()
        if data.get("message") != "accept":
            raise RuntimeError(f"Invalid EA code: {data}")
        self.is_martingale = bool(data.get("martingale") or data.get("ea_martingale"))
        self.ea_name = str(data.get("ea_name") or "")
        LOG.info("Authenticated — EA: %s | copy trading: %s", self.ea_name, self.is_martingale)

    def post_signal(self, payload: dict) -> bool:
        if time.time() - self.last_publish_at < THROTTLE_SECONDS:
            return False
        url = f"{self.api_base}/post-signal"
        body = {"ea_secret": self.ea_code, "signal": payload}
        resp = self.session.post(url, json=body, timeout=20)
        LOG.info("Publish HTTP %s — %s", resp.status_code, resp.text[:300])
        if resp.status_code == 200 and resp.json().get("message") == "accept":
            self.last_publish_at = time.time()
            return True
        return False

    def close_signal(self, asset: str) -> None:
        url = f"{self.api_base}/close-signal"
        body = {"ea_secret": self.ea_code, "asset": asset}
        resp = self.session.post(url, json=body, timeout=20)
        LOG.info("Close %s — HTTP %s %s", asset, resp.status_code, resp.text[:200])

    def _volume_digits(self, symbol: str) -> int:
        info = mt5.symbol_info(symbol)
        if info is None or info.volume_step <= 0:
            return 2
        step = info.volume_step
        digits = 0
        while step < 1 and digits < 8:
            step *= 10
            digits += 1
        return digits

    def _position_payload(self, symbol: str, position) -> dict:
        info = mt5.symbol_info(symbol)
        digits = info.digits if info else 5
        action = "buy" if position.type == mt5.POSITION_TYPE_BUY else "sell"
        lot = f"{position.volume:.{self._volume_digits(symbol)}f}"
        return {
            "asset": symbol,
            "type": "all",
            "action": action,
            "price": f"{position.price_open:.{digits}f}",
            "tp": f"{position.tp:.{digits}f}" if position.tp else "0",
            "sl": f"{position.sl:.{digits}f}" if position.sl else "0",
            "lot": lot,
        }

    def scan_positions(self) -> None:
        positions = mt5.positions_get()
        live_ids: Set[int] = set()

        if positions:
            for pos in positions:
                pos_id = int(pos.identifier)
                live_ids.add(pos_id)
                symbol = str(pos.symbol)

                if pos_id in self.published_ids:
                    self.tracked[pos_id] = TrackedPosition(pos_id, symbol, "buy" if pos.type == mt5.POSITION_TYPE_BUY else "sell")
                    continue

                payload = self._position_payload(symbol, pos)
                LOG.info("New position %s — %s %s", pos_id, payload["action"], symbol)
                if self.post_signal(payload):
                    self.published_ids.add(pos_id)
                    self.tracked[pos_id] = TrackedPosition(pos_id, symbol, payload["action"])
                    LOG.info("Published position %s", pos_id)
                else:
                    LOG.warning("Publish skipped/failed for %s — will retry", pos_id)

        closed = [pid for pid in list(self.tracked.keys()) if pid not in live_ids]
        for pid in closed:
            tracked = self.tracked.pop(pid, None)
            self.published_ids.discard(pid)
            if tracked:
                LOG.info("Position closed — removing signal for %s", tracked.asset)
                self.close_signal(tracked.asset)

    def run(self) -> None:
        self.authenticate()
        LOG.info("Watching MT5 positions (poll every %ss)…", POLL_SECONDS)
        while True:
            try:
                self.scan_positions()
            except requests.RequestException as exc:
                LOG.error("Network error: %s", exc)
            except Exception as exc:
                LOG.exception("Scan error: %s", exc)
            time.sleep(POLL_SECONDS)


def init_mt5() -> None:
    login = os.getenv("MT5_LOGIN")
    password = os.getenv("MT5_PASSWORD")
    server = os.getenv("MT5_SERVER")
    path = os.getenv("MT5_PATH")

    kwargs = {}
    if path:
        kwargs["path"] = path
    if login and password:
        kwargs["login"] = int(login)
        kwargs["password"] = password
        if server:
            kwargs["server"] = server

    if not mt5.initialize(**kwargs):
        code, msg = mt5.last_error()
        raise RuntimeError(f"MT5 initialize failed: {code} {msg}")

    info = mt5.account_info()
    if info:
        LOG.info("MT5 connected — %s #%s (%s)", info.name, info.login, info.server)
    else:
        LOG.warning("MT5 initialized but account_info() is empty — attach terminal login")


def main() -> int:
    logging.basicConfig(
        level=logging.INFO,
        format="%(asctime)s [%(levelname)s] %(message)s",
        datefmt="%H:%M:%S",
    )

    if not EA_CODE:
        LOG.error("Set NEXTRADE_EA_CODE to your automation secret from admin → EAs → Secret code")
        return 1

    init_mt5()
    bridge = NexTradeCopyBridge(EA_CODE, API_BASE)
    try:
        bridge.run()
    except KeyboardInterrupt:
        LOG.info("Stopped.")
    finally:
        mt5.shutdown()
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
