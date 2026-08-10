#!/usr/bin/env python3
# -*- coding: utf-8 -*-
"""
generate_metabolite_pages.py
=============================
用途：
    批次生成／更新 dbMetabolite/metabolite/<DMTDB_ID>/index.php

背景：
    每個代謝物頁面的 PHP 程式碼骨架其實完全相同（代謝物 ID 是從網址動態
    抓取，不是寫死在檔案裡），唯一因代謝物而異的是 Identification 與
    Information 這兩個區塊 —— 目前先留空。
    而這份「共用模板」本身會持續更動（例如之後新增免疫資料相關的查詢或
    區塊），所以腳本需要支援：
      (a) 貼上目前版本的模板，一次批次生成所有代謝物頁面
      (b) 模板改版後，重新貼上新版模板，一次性更新「所有已存在」的頁面
      (c) 若某個代謝物頁面之後想個別客製化內容，可單獨覆蓋，且未來批次
          更新模板時不會誤蓋掉這頁客製化的內容（自動偵測、不需要自己記）

自動保護機制：
    批次模式（用模板生成）寫入的檔案，會自動嵌入一行隱藏的 PHP 註解標記
    （不影響網頁顯示）。之後重新批次套用新模板時：
      - 該頁面有標記 → 代表目前內容仍是模板產生的，可放心覆蓋成新模板
      - 該頁面沒有標記 → 代表曾被單筆模式（--id/--file）客製化過，
        會自動跳過保護，不會被批次更新覆蓋（除非加上 --force）

資料夾結構範例（執行前）：
    dbMetabolite/
    ├── template.php             <-- 貼上目前（或最新版）的共用模板 PHP 程式碼
    ├── metabolite_ids.txt       <-- （可選）代謝物 ID 清單，一行一個
    ├── metabolite/               <-- 腳本會自動建立/更新這裡
    ├── generate_metabolite_pages.py
    └── ...

執行方式：
    # 1) 首次生成：用 ID 清單 + template.php 模板，批次建立所有頁面
    python generate_metabolite_pages.py --template template.php --ids-file metabolite_ids.txt

    # 2) 也可以直接在指令列列出 ID（逗號分隔）
    python generate_metabolite_pages.py --template template.php --ids dmtdb000001,dmtdb000002

    # 3) 模板改版後（例如新增了免疫資料區塊），想一次更新「所有已存在」的
    #    代謝物頁面 —— 不用重新列出 ID 清單，直接掃描 metabolite/ 底下現有
    #    的資料夾。曾被單筆模式客製化過的頁面會自動被跳過、不受影響：
    python generate_metabolite_pages.py --template template.php --update-existing

    # 4) 單筆模式：只想生成/覆蓋「單一」一個代謝物頁面（例如貼上該代謝物
    #    個別客製化後的完整 PHP 程式碼，例如已手動填入 Identification 資料）：
    python generate_metabolite_pages.py --id dmtdb000001 --file my_custom_page.php

    # 5) 若想無視保護機制、強制用新模板覆蓋「所有」頁面（含已客製化的）：
    python generate_metabolite_pages.py --template template.php --update-existing --force

    # 6) 若想完全不覆蓋任何已存在的頁面（不論是否有標記）：
    python generate_metabolite_pages.py --template template.php --update-existing --skip-existing

    # 7) 先看看會處理哪些檔案，不真的寫入磁碟（乾跑模式）：
    python generate_metabolite_pages.py --template template.php --update-existing --dry-run
"""

import argparse
import re
import sys
from pathlib import Path

# 代謝物 ID 格式檢查（例如 dmtdb000001），可依實際命名規則調整
ID_PATTERN = re.compile(r"^[A-Za-z0-9_-]+$")

# 自動標記：批次模式（模板生成）寫入的檔案會含有這個 token，
# 用來判斷「是否仍是模板產生的內容、可放心覆蓋」。
MARKER_TOKEN = "AUTO-GENERATED-FROM-TEMPLATE"
MARKER_COMMENT = (
    "// [{token}] 此頁面由 generate_metabolite_pages.py 自動產生自共用模板；\n"
    "// 範本更新後重新執行批次模式（--update-existing）會被覆蓋更新。\n"
    "// 若要個別客製化此頁面，請改用單筆模式（--id/--file），\n"
    "// 該模式產生的頁面不含此標記，未來批次更新不會覆蓋它。"
).format(token=MARKER_TOKEN)


def normalize_id(raw_id: str) -> str:
    """將代謝物 ID 正規化為小寫（配合資料夾命名慣例：dmtdb000001）。"""
    return raw_id.strip().lower()


def is_valid_id(raw_id: str) -> bool:
    return bool(raw_id) and bool(ID_PATTERN.match(raw_id))


def has_marker(content: str) -> bool:
    return MARKER_TOKEN in content


def add_marker(content: str) -> str:
    """在模板內容的 `<?php` 開頭標籤之後插入自動產生標記註解。"""
    if has_marker(content):
        return content
    stripped = content.lstrip()
    if stripped.startswith("<?php"):
        idx = content.index("<?php") + len("<?php")
        return content[:idx] + "\n" + MARKER_COMMENT + content[idx:]
    # 若模板不是以 <?php 開頭（非預期情況），直接在最前面加上 PHP 註解區塊
    return "<?php\n" + MARKER_COMMENT + "\n?>\n" + content


def load_ids_from_file(ids_file: Path):
    """從清單檔讀取代謝物 ID（一行一個，忽略空行與 # 開頭的註解行）。"""
    ids = []
    for line in ids_file.read_text(encoding="utf-8").splitlines():
        line = line.strip()
        if not line or line.startswith("#"):
            continue
        ids.append(line)
    return ids


def load_ids_from_existing(output_root: Path):
    """掃描 metabolite/ 底下現有的子資料夾，回傳其名稱作為 ID 清單。
    用於模板改版後，重新套用到所有已存在的代謝物頁面。"""
    if not output_root.exists():
        return []
    return sorted(p.name for p in output_root.iterdir() if p.is_dir())


def write_metabolite_page(
    metabolite_id: str,
    content: str,
    output_root: Path,
    mode: str,                      # "template"（批次/模板模式）或 "single"（單筆客製化模式）
    skip_existing: bool = False,
    force: bool = False,
    dry_run: bool = False,
):
    """
    在 output_root/<metabolite_id>/index.php 寫入內容。
    回傳狀態字串："created" / "updated" / "skipped" / "skipped-customized" / "dry-run"
    """
    folder_id = normalize_id(metabolite_id)
    if not is_valid_id(folder_id):
        raise ValueError(f"不合法的代謝物 ID：{metabolite_id!r}")

    target_dir = output_root / folder_id
    target_file = target_dir / "index.php"
    already_exists = target_file.exists()

    if already_exists and not force:
        if skip_existing:
            return "skipped"
        if mode == "template":
            existing_content = target_file.read_text(encoding="utf-8")
            if not has_marker(existing_content):
                # 曾被單筆模式客製化過，自動保護、不覆蓋
                return "skipped-customized"

    final_content = add_marker(content) if mode == "template" else content

    if dry_run:
        return "dry-run"

    target_dir.mkdir(parents=True, exist_ok=True)
    target_file.write_text(final_content, encoding="utf-8")

    return "updated" if already_exists else "created"


def main():
    parser = argparse.ArgumentParser(
        description="批次生成／更新 dbMetabolite/metabolite/<id>/index.php 頁面",
        formatter_class=argparse.RawDescriptionHelpFormatter,
    )
    parser.add_argument(
        "--template",
        default="template.php",
        help="共用模板 PHP 檔案路徑（貼上目前版本的個別頁面程式碼）。預設：template.php",
    )
    parser.add_argument(
        "--output",
        default="metabolite",
        help="輸出的 metabolite 資料夾路徑。預設：metabolite",
    )
    parser.add_argument(
        "--ids-file",
        default=None,
        help="代謝物 ID 清單檔案路徑（一行一個 ID）",
    )
    parser.add_argument(
        "--ids",
        default=None,
        help="直接以逗號分隔指定代謝物 ID，例如：dmtdb000001,dmtdb000002",
    )
    parser.add_argument(
        "--update-existing",
        action="store_true",
        help="不需提供 ID 清單，直接掃描 metabolite/ 底下所有現有資料夾並套用新模板（適合模板改版後的批次更新；曾被客製化的頁面會自動被跳過）",
    )
    parser.add_argument(
        "--id",
        default=None,
        help="單筆模式：指定要生成/覆蓋的代謝物 ID（需搭配 --file 使用）",
    )
    parser.add_argument(
        "--file",
        default=None,
        help="單筆模式：指定該代謝物頁面 PHP 原始碼所在的檔案路徑（例如個別客製化後的內容）",
    )
    parser.add_argument(
        "--skip-existing",
        action="store_true",
        help="若 metabolite/<id>/index.php 已存在，一律跳過、不覆蓋（不論是否被客製化過）",
    )
    parser.add_argument(
        "--force",
        action="store_true",
        help="強制覆蓋，即使該頁面曾被單筆模式客製化過也覆蓋成新模板（危險，會遺失客製化內容，請謹慎使用）",
    )
    parser.add_argument(
        "--dry-run",
        action="store_true",
        help="僅顯示將會處理哪些代謝物頁面，不實際寫入檔案",
    )

    args = parser.parse_args()
    output_root = Path(args.output)

    # ── 單筆模式：--id + --file（用於個別客製化單一頁面）──────────
    if args.id or args.file:
        if not (args.id and args.file):
            print("錯誤：--id 與 --file 必須同時提供。")
            sys.exit(1)

        file_path = Path(args.file)
        if not file_path.exists():
            print(f"錯誤：找不到檔案 {file_path}")
            sys.exit(1)

        content = file_path.read_text(encoding="utf-8")
        status = write_metabolite_page(
            args.id, content, output_root, mode="single",
            skip_existing=args.skip_existing, force=args.force, dry_run=args.dry_run,
        )
        print(f"[{status}] {normalize_id(args.id)}/index.php  (來源：{file_path})")
        return

    # ── 批次模式：需要模板 + ID 清單來源 ─────────────────────────
    template_path = Path(args.template)
    if not template_path.exists():
        print(f"錯誤：找不到模板檔案 {template_path}")
        print("請先把您的共用模板 PHP 程式碼存成該檔案（或用 --template 指定路徑）。")
        sys.exit(1)
    template_content = template_path.read_text(encoding="utf-8")

    # ID 清單來源優先順序：--ids > --ids-file > --update-existing
    ids = []
    source_desc = ""
    if args.ids:
        ids = [x.strip() for x in args.ids.split(",") if x.strip()]
        source_desc = "指令列 --ids 參數"
    elif args.ids_file:
        ids_file = Path(args.ids_file)
        if not ids_file.exists():
            print(f"錯誤：找不到 ID 清單檔案 {ids_file}")
            sys.exit(1)
        ids = load_ids_from_file(ids_file)
        source_desc = f"清單檔案 {ids_file}"
    elif args.update_existing:
        ids = load_ids_from_existing(output_root)
        source_desc = f"掃描現有資料夾 {output_root}"
    else:
        print("錯誤：批次模式需要指定代謝物 ID 的來源，請使用以下其中一種：")
        print("  --ids dmtdb000001,dmtdb000002   直接指定 ID（逗號分隔）")
        print("  --ids-file metabolite_ids.txt   從清單檔讀取 ID（一行一個）")
        print("  --update-existing                套用到 metabolite/ 底下所有現有資料夾（模板改版後使用）")
        sys.exit(1)

    if not ids:
        print(f"從「{source_desc}」沒有讀到任何代謝物 ID，請確認來源內容。")
        sys.exit(0)

    print(f"模板檔案：{template_path.resolve()}")
    print(f"輸出資料夾：{output_root.resolve()}")
    print(f"ID 來源：{source_desc}")
    print(f"共 {len(ids)} 個代謝物，開始處理...\n")

    summary = {"created": 0, "updated": 0, "skipped": 0, "skipped-customized": 0, "dry-run": 0}
    errors = []

    for metabolite_id in ids:
        try:
            status = write_metabolite_page(
                metabolite_id, template_content, output_root, mode="template",
                skip_existing=args.skip_existing, force=args.force, dry_run=args.dry_run,
            )
            summary[status] += 1
            print(f"[{status:18s}] {normalize_id(metabolite_id)}/index.php")
        except ValueError as e:
            errors.append((metabolite_id, str(e)))
            print(f"[錯誤              ] {metabolite_id}: {e}")

    print("\n=== 完成 ===")
    for key, count in summary.items():
        if count:
            print(f"  {key}: {count} 筆")
    if summary["skipped-customized"]:
        print("  （skipped-customized 為自動保護：這些頁面曾被單筆模式客製化過，未套用新模板；如確定要覆蓋，加上 --force）")
    if errors:
        print(f"\n共有 {len(errors)} 個 ID 處理失敗，請檢查 ID 命名格式。")


if __name__ == "__main__":
    main()
