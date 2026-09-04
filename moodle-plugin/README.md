# MTPC Moodle teaching bridge

Plugin này bổ sung một External Function để dashboard MTPC tạo bài giảng thật
trong khoá học Moodle dưới dạng **Page** hoặc **URL**. Nó dùng API nội bộ
`add_moduleinfo()` của Moodle nên hoạt động như khi giáo viên tạo activity trong
course, thay vì ghi trực tiếp vào database.

## Cài trên Moodle

1. Với Moodle 5.1+, copy thư mục `local/mtpcbridge` vào thư mục code Moodle
   `public/local/` (Document Root của website là thư mục `public/`).
2. Đăng nhập Moodle bằng tài khoản quản trị và mở **Site administration →
   Notifications**, hoàn tất nâng cấp plugin.
3. Mở **Site administration → Server → Web services → External services**,
   chọn service đang cấp cho token MTPC và thêm các function:
   `local_mtpcbridge_create_lecture`, `local_mtpcbridge_create_file_lecture`
   và `local_mtpcbridge_create_announcement`.
4. Đảm bảo service account của token có quyền `moodle/course:manageactivities`
   trong các khoá học cần tạo bài giảng. Với `Announcements`, tài khoản cần
   quyền `mod/forum:addnews` tại chính forum Announcements.
5. Giữ nguyên token trong `/home/mtpc/private/moodle-config.php`; không đưa
   token vào frontend hoặc GitHub.

## Dùng trong dashboard

Sau khi deploy dashboard và cài plugin Moodle, nói với orb Nhi:

```text
Đăng bài giảng tuần 1 vào Course 2, tiêu đề Hệ điều hành, nội dung là ...
Đăng video bài giảng tuần 2 vào Course 2: https://example.com/video
```

Hoặc bấm **Moodle → Đăng bài giảng**, chọn Course, Category/chủ đề, chọn file
từ máy rồi bấm **Đăng bài giảng**. Dashboard sẽ tạo một hoạt động Page trong
đúng section và đính kèm file để học viên mở trực tiếp trong Moodle. File upload
hỗ trợ PDF, Word, PowerPoint, TXT/Markdown/HTML và JPG/PNG, tối đa 20 MB.

Các bài Page/URL được tạo bằng orb vẫn đi qua **Phê duyệt AI**; form upload dành
cho quản trị viên đã đăng nhập và gửi trực tiếp sau khi bấm nút xác nhận.
