# Xử lý file trong chat quản trị

Nói/nhập “Tóm tắt tài liệu thành một trang Word”, chọn file ngay trong chat,
rồi nói/nhập “xử lý”. Kết quả là bản tóm tắt và nút tải file mới.
Nút “Dùng file này” chọn kết quả cho yêu cầu tiếp theo; không tự đăng/gửi đi.
Giữ giao diện xanh và luồng đăng Moodle hiện có.

## Triển khai

1. cPanel Git: Update from Remote, sau đó Deploy HEAD Commit.
2. PHP cần cURL; đọc Office cần ZIP (ZipArchive) và DOM; tạo DOCX cần ZIP.
3. Dùng GEMINI_API_KEY từ môi trường hoặc biến `$GEMINI_API_KEY` trong
   `/home/mtpc/private/gemini-config.php`, giống endpoint chat hiện có.
4. Endpoint dùng đăng nhập Directory Privacy, DB và vai trò admin hiện có.
5. Giới hạn hosting `upload_max_filesize` tối thiểu 10M, `post_max_size` lớn hơn
   (ví dụ 12M); proxy cần cho phép xử lý tối đa khoảng 150 giây.
6. Tải lại trang để nạp script/công cụ Live mới. Thử một TXT nhỏ trước.

## Giới hạn và dữ liệu

- Một file/lần, tối đa 10 MB. Văn bản trích xuất tối đa 800 KB.
- PDF và PNG/JPEG/WebP gửi nội dung trực tiếp tới Gemini.
- TXT/MD/CSV/HTML cần UTF-8. DOCX/PPTX/XLSX chỉ trích xuất văn bản/giá trị ô,
  không lấy ảnh nhúng, biểu đồ, định dạng, bình luận hoặc các phần Office khác.
  Công thức Excel không được chạy lại. Với bố cục/ảnh, nên xuất PDF đầu vào.
- Không đọc DOC/PPT/XLS cũ: chuyển sang định dạng mới hoặc PDF.
- Xuất DOCX dạng đoạn văn đơn giản, TXT, MD hoặc CSV; không xuất PDF/XLSX/PPTX.
- File đầu vào được gửi tới Gemini để xử lý; không lưu lâu dài trên ứng dụng.
  File kết quả ở bộ nhớ trình duyệt: tải xuống trước khi tải lại/đóng trang.
- Không thực thi macro/code trong file. CSV đầu ra vô hiệu hóa tiền tố công thức.
- Không ghi nội dung tài liệu vào audit, chỉ kích thước và định dạng đầu ra.
- Kết quả AI cần được kiểm tra. Đầu ra bị cắt/không hợp lệ không tạo nút tải.

## Kiểm tra local

`php tests/ai-file-lib-test.php` (bật ZIP và DOM).

`node tests/ai-file-chat-test.cjs` kiểm tra DOM/fetch giả lập, không gọi Gemini.

Chưa thay thế kiểm tra end-to-end trên hosting với tài khoản được cấp quyền.
