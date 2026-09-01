#!/usr/bin/env python3
"""Extract DDL-only schema from a MariaDB dump (no INSERT data)."""
from __future__ import annotations

import re
import sys
from pathlib import Path


def extract_schema(text: str) -> str:
    out: list[str] = [
        "-- NexTradeAI schema (DDL only)",
        "SET NAMES utf8mb4;",
        "SET FOREIGN_KEY_CHECKS=0;",
        "",
    ]
    skip_insert = False
    in_create = False

    for line in text.splitlines():
        if line.startswith("lines "):
            continue
        if re.match(r"^e sandbox mode \*/", line):
            continue

        if skip_insert:
            if line.rstrip().endswith(";"):
                skip_insert = False
            continue

        if line.startswith("INSERT INTO") or line.startswith("LOCK TABLES"):
            skip_insert = True
            continue

        if line.startswith("/*!40000 ALTER TABLE") and "DISABLE KEYS" in line:
            continue
        if line.startswith("/*!40000 ALTER TABLE") and "ENABLE KEYS" in line:
            continue

        if line.startswith("DROP TABLE"):
            out.append(line)
            in_create = False
            continue

        if line.startswith("CREATE TABLE"):
            in_create = True
            out.append(line)
            continue

        if in_create:
            out.append(line)
            if ") ENGINE=" in line:
                in_create = False
            continue

        if line.startswith("/*!40101") or line.startswith("/*!40014") or line.startswith("/*!40003") or line.startswith("/*!40111"):
            out.append(line)
            continue

        if line.startswith("--"):
            out.append(line)

    out.extend(["", "SET FOREIGN_KEY_CHECKS=1;", ""])
    return "\n".join(out)


def main() -> int:
    if len(sys.argv) != 2:
        print(f"Usage: {sys.argv[0]} /path/to/dump.sql", file=sys.stderr)
        return 1
    path = Path(sys.argv[1])
    if not path.is_file():
        print(f"Not found: {path}", file=sys.stderr)
        return 1
    print(extract_schema(path.read_text(encoding="utf-8", errors="replace")))
    return 0


if __name__ == "__main__":
    raise SystemExit(main())
