# MTPC Moodle teaching bridge

Plugin này bổ sung một External Function để dashboard MTPC tạo bài giảng thật
trong khoá học Moodle dưới dạng **Page** hoặc **URL**. Nó dùng API nội bộ
`add_moduleinfo()` của Moodle nên hoạt động như khi giáo viên tạo activity trong
course, thay vì ghi trực tiếp vào database.

## Cài trên Moodle

1. Copy thư mục `local/mtpcbridge` vào thư mục Moodle `local/`.
2. Đăng nhập Moodle bằng tài khoản quản trị và mở **Site administration →
   Notifications**, hoàn tất nâng cấp plugin.
3. Mở **Site administration → Server → Web services → External services**,
   chọn service đang cấp cho token MTPC và thêm function:
   `local_mtpcbridge_create_lecture`.
4. Đảm bảo service account của token có quyền `moodle/course:manageactivities`
   trong các khoá học cần đăng bài.
5. Giữ nguyên token trong `/home/mtpc/private/moodle-config.php`; không đưa
   token vào frontend hoặc GitHub.

## Dùng trong dashboard

Sau khi deploy dashboard và cài plugin Moodle, nói với orb Nhi:

```text
Đăng bài giảng tuần 1 vào Course 2, tiêu đề Hệ điều hành, nội dung là ...
Đăng video bài giảng tuần 2 vào Course 2: https://example.com/video
```

Dashboard sẽ tạo yêu cầu trong **Phê duyệt AI**. Sau khi duyệt, bài giảng sẽ
xuất hiện trong đúng section của khoá học.
