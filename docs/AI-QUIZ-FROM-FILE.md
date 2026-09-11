# Tạo bài kiểm tra Moodle từ file câu hỏi

Admin có thể chọn file câu hỏi trong Orb quản trị và nói “tạo bài kiểm tra từ file này”. Hệ thống sẽ:

1. Gửi file tới AI để đọc và chuẩn hóa các câu `multichoice`, `truefalse`, `shortanswer`.
2. Hiện bản nháp gồm tên bài, tóm tắt và danh sách câu hỏi để admin kiểm tra.
3. Chỉ sau khi admin bấm “Xác nhận tạo Quiz trên Moodle” hoặc nói “tạo đi”, hệ thống mới tạo Quiz, nhập câu hỏi vào Question Bank và gắn câu hỏi vào Quiz.

## Định dạng đầu vào

PDF, DOCX, PPTX, XLSX, TXT, Markdown, CSV, HTML và ảnh PNG/JPEG/WebP; tối đa 10 MB cho xử lý AI. File phải có nội dung câu hỏi và đáp án rõ ràng. Câu không xác định được đáp án sẽ bị bỏ qua và nêu trong bản tóm tắt.

## Triển khai Moodle

- Deploy mã admin và plugin `local_mtpcbridge`.
- Moodle sẽ tự chạy nâng cấp plugin từ version `2026091101` và thêm function `local_mtpcbridge_create_quiz_from_questions` vào external service `dashboard`.
- Kiểm tra token Moodle của dashboard có function này. Nếu service không tự cập nhật, vào Moodle: Site administration → Server → Web services → External services → `dashboard`, rồi thêm function trên.
- Purge Moodle cache sau khi nâng cấp plugin.
- Trình PHP của Moodle cần có các thành phần question/quiz chuẩn; đọc Office ở endpoint admin cần `ZipArchive` và `DOM` như luồng đọc file hiện có.

## Lưu ý

Bài kiểm tra được tạo ở trạng thái hiển thị sau xác nhận. AI không chấm hay tự sửa nội dung sau khi đã tạo. Giáo viên nên mở Quiz trong Moodle để kiểm tra lại câu hỏi, đáp án, điểm và thời gian trước khi cho học viên làm.
