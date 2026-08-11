#!/usr/bin/env python3
# =============================================
# generate_clinicaldata_pages.py
# 用途：讀取 template.php + patient_ids.txt，
#       在 clinicaldata/<病人ID>/index.php 產生個別頁面
#
# 注意：template.php 的內容對所有病人是「完全相同」的，
#       因為 index.php 內部是用資料夾名稱 (basename) 動態
#       取得病人 ID，所以這裡不需要做任何字串替換，
#       只是單純把 template 複製到每個病人資料夾底下。
# =============================================

import os
import sys

# ---------- 可依需求調整的路徑設定 ----------
# 這支腳本預期放在 dbMetabolite/clinicaldata/ 底下執行
BASE_DIR      = os.path.dirname(os.path.abspath(__file__))
TEMPLATE_PATH = os.path.join(BASE_DIR, "template.php")
IDS_PATH      = os.path.join(BASE_DIR, "patient_ids.txt")
OUTPUT_DIR    = BASE_DIR   # 產生的資料夾會直接建立在 clinicaldata/ 底下
# ---------------------------------------------


def load_patient_ids(path: str) -> list[str]:
    """讀取病人 ID 清單，一行一個 ID，忽略空行與前後空白"""
    if not os.path.isfile(path):
        sys.exit(f"[錯誤] 找不到病人 ID 清單檔案：{path}")

    with open(path, "r", encoding="utf-8") as f:
        ids = [line.strip() for line in f if line.strip()]

    if not ids:
        sys.exit(f"[錯誤] {path} 內沒有任何病人 ID")

    return ids


def load_template(path: str) -> str:
    """讀取模板 php 內容"""
    if not os.path.isfile(path):
        sys.exit(f"[錯誤] 找不到模板檔案：{path}")

    with open(path, "r", encoding="utf-8") as f:
        return f.read()


def generate_pages(patient_ids: list[str], template_content: str,
                    output_dir: str, overwrite: bool = True) -> None:
    created, skipped, updated = 0, 0, 0

    for pid in patient_ids:
        patient_dir = os.path.join(output_dir, pid)
        os.makedirs(patient_dir, exist_ok=True)

        index_path = os.path.join(patient_dir, "index.php")
        exists = os.path.isfile(index_path)

        if exists and not overwrite:
            skipped += 1
            continue

        with open(index_path, "w", encoding="utf-8") as f:
            f.write(template_content)

        if exists:
            updated += 1
        else:
            created += 1

    print("=" * 50)
    print(f"完成！共處理 {len(patient_ids)} 位病人")
    print(f"  新建立: {created}")
    print(f"  已更新: {updated}")
    print(f"  已跳過(未覆蓋): {skipped}")
    print("=" * 50)


def main():
    overwrite = True
    if "--no-overwrite" in sys.argv:
        overwrite = False
        print("[模式] 已存在的 index.php 將不會被覆蓋")
    else:
        print("[模式] 已存在的 index.php 將被覆蓋更新（如需保留舊檔請加參數 --no-overwrite）")

    patient_ids = load_patient_ids(IDS_PATH)
    template_content = load_template(TEMPLATE_PATH)

    print(f"讀取到 {len(patient_ids)} 個病人 ID")
    print(f"模板檔案：{TEMPLATE_PATH}")
    print(f"輸出目錄：{OUTPUT_DIR}\n")

    generate_pages(patient_ids, template_content, OUTPUT_DIR, overwrite=overwrite)


if __name__ == "__main__":
    main()
