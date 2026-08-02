---
name: token-saver
description: Chế độ trả lời tối giản để tiết kiệm token — bỏ xã giao, bỏ giải thích lặp lại đề bài, chỉ xuất phần code đã đổi. Dùng khi người dùng gõ "/token-saver" hoặc yêu cầu "trả lời ngắn gọn/tiết kiệm token".
---

Khi skill này đang bật, mọi câu trả lời phải theo đúng 4 quy tắc sau, không có ngoại lệ trừ khi người dùng tắt skill:

1. Không xã giao — không "Chào bạn", "Rất vui được giúp", "Dưới đây là...".
2. Không diễn giải lại đề bài hoặc lặp lại điều người dùng đã nói.
3. Không viết lại toàn bộ file nếu chỉ sửa một đoạn nhỏ — dùng Edit (diff-style), không dùng Write để in lại cả file trong câu trả lời.
4. Đầu ra chỉ gồm:
   - Đoạn code đã đổi (hoặc dùng comment `// ... giữ nguyên ...` cho phần không đổi khi bắt buộc phải trích dẫn nguyên khối).
   - Giải thích dưới 3 dòng, chỉ khi thật sự cần thiết để hiểu lý do sửa.

Áp dụng cho toàn bộ phần *text hiển thị cho người dùng* trong response — không áp dụng cho nội dung file được ghi ra (code trong file vẫn viết đầy đủ, đúng chuẩn `.claude/rules/coding-standards.md`, không cắt bớt).

Skill này chỉ đổi văn phong/độ dài câu trả lời, không đổi quy tắc an toàn dữ liệu hay quy trình bắt buộc khác trong `.claude/CLAUDE.md` và `.claude/rules/` — các cảnh báo bắt buộc (VD: DB đang trỏ Production) vẫn phải nêu, nhưng nêu ở dạng 1 dòng ngắn nhất có thể.
