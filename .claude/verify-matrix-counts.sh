#!/bin/bash
# Kiểm tra tự động số liệu kiểm kê của vba-migration-matrix.md
# Chạy: bash .claude/verify-matrix-counts.sh
# Mục đích: bảo đảm "tổng số dòng phân loại theo Trạng thái" = "tổng số dòng traceability"
# trong bảng chính. Nếu FAIL, nghĩa là có dòng thiếu/sai định dạng cột Trạng thái
# (ví dụ: bị bôi đậm **STATUS**, gán 2 trạng thái cùng lúc, hoặc để trống "—")
# cần chuẩn hóa thủ công trước khi tin tưởng số liệu tổng hợp.
#
# Quy ước: 1 dòng bảng = 1 "traceability row" (có thể đại diện nhiều "physical procedure"
# nếu Procedure ghi rõ dạng "(xN, gộp)", "(N handler)", "(N wrapper)", hoặc ID dạng
# "NNN…MMM" (range). Xem đầu vba-migration-matrix.md mục "Phương pháp đếm" để biết
# cách quy đổi traceability row -> physical procedure count.

set -euo pipefail
MTX="$(dirname "$0")/vba-migration-matrix.md"
STATUSES=(FULLY_MIGRATED MIGRATED_NO_TEST PARTIALLY_MIGRATED MISSING REPLACED_EQUIVALENTLY MERGED DEPRECATED_CONFIRMED DEAD_CODE_CANDIDATE NEEDS_BUSINESS_CONFIRMATION TARGET_DESIGNED SCHEMA_PROPOSED BLOCKED NOT_REQUIRED_CONFIRMED LEGACY_BUG_NOT_MIGRATED)

TOTAL=0
echo "=== Phân bố theo Trạng thái (đếm bằng khớp cell chính xác '| STATUS |') ==="
for s in "${STATUSES[@]}"; do
  n=$(grep -cE "^\| VBA-.*\| $s \|" "$MTX" || true)
  printf "%-30s %d\n" "$s" "$n"
  TOTAL=$((TOTAL + n))
done

ROWS=$(grep -c "^| VBA-" "$MTX")
UNMATCHED=$((ROWS - TOTAL))

echo ""
echo "Tổng số dòng đã phân loại (SUM):  $TOTAL"
echo "Tổng số dòng traceability (ROWS): $ROWS"
echo "Chênh lệch (UNMATCHED):           $UNMATCHED"
echo ""

if [ "$TOTAL" -eq "$ROWS" ]; then
  echo "KET QUA: PASS - moi dong deu co dung 1 trang thai hop le, khop 100% tong so dong."
  exit 0
else
  echo "KET QUA: FAIL - co $UNMATCHED dong khong khop dinh dang trang thai chuan."
  echo "Chay lenh sau de liet ke cac dong can chuan hoa thu cong:"
  echo "  grep -E \"^\\| VBA-\" \"$MTX\" | grep -vE \"\\| ($(IFS='|'; echo "${STATUSES[*]}")) \\|\""
  exit 1
fi
