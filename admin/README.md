# MTPC Admin

Dashboard quản trị nội bộ của Trường Trung cấp Miền Tây, chạy tại
`https://admin.mtpc.edu.vn`.

Dashboard là ứng dụng HTML/CSS/JavaScript thuần, được bảo vệ bằng cPanel
Directory Privacy và kết nối MySQL qua các API PHP tương thích PHP 5.6. Phần
trợ lý giọng nói public nằm ở thư mục gốc của repository; tài liệu của phần đó
ở [`../README.md`](../README.md).

## Chức năng quản trị

- Tổng quan số liệu tuyển sinh.
- Quản lý ngành đào tạo, tin tức và hồ sơ tư vấn.
- Quản lý hồ sơ sinh viên từ MySQL.
- Nhập sinh viên từ XLSX/CSV kèm ảnh chân dung hoặc giấy tờ.
- Điểm danh, cảnh báo điểm danh và lịch sử học tập.
- Học phí, công nợ và ghi nhận thanh toán.
- Tài khoản quản trị, vai trò và phân quyền.
- Phê duyệt nội dung AI, audit log và dữ liệu knowledge.
- Đọc email quản trị qua IMAP và gửi email qua SMTP.
- Đọc tin nhắn Zalo OA, trả lời trực tiếp và cấp quyền điều khiển OA.
- Kết nối Moodle tại `moodle.mtpc.edu.vn`: kiểm tra kết nối, xem khoá học,
  nội dung, thành viên, tìm tài khoản, tạo khoá học và ghi danh.
- AI Copilot và SEO Studio hỗ trợ tạo nội dung nháp.

## Kiến trúc kỹ thuật

| Lớp | Công nghệ |
|---|---|
| UI | `admin/index.html`, HTML/CSS/JavaScript thuần |
| Auth | cPanel Directory Privacy, username từ `REMOTE_USER` |
| Backend | PHP 5.6-compatible, PDO và cURL |
| Database | MySQL/MariaDB, schema `database/student_management_v2.sql` |
| Text AI | Gemini `gemini-3.1-flash-lite` qua API backend |
| Zalo AI | Zalo OA Webhook + Gemini `gemini-3.1-flash-lite` |
| Moodle | Moodle Web Services REST API qua PHP/cURL, token service account |
| Storage phụ | JSON private cho message, operator, link request và pending command |
| Deploy | cPanel Git Version Control + `.cpanel.yml` |

Không có API key hoặc mật khẩu nào được đặt trong `admin/index.html`. Các API
đọc secret từ `/home/mtpc/private` hoặc biến môi trường trên server.

## Cấu trúc thư mục

```text
admin/index.html              Giao diện dashboard và AI Copilot
admin/api/_student_bootstrap.php
                               Kết nối DB, xác định user và permission
admin/api/students.php        CRUD và danh sách hồ sơ sinh viên
admin/api/student-ops.php    Điểm danh, học phí, học tập, user và audit
admin/api/student-import.php Nhập XLSX/CSV + ảnh
admin/api/student-file.php   Đọc file sinh viên sau khi đã xác thực
admin/api/operations.php     Hàng chờ/phê duyệt và nhật ký thao tác
admin/api/email.php           IMAP đọc email + SMTP gửi email
admin/api/zalo-oa.php        Zalo message log, send, operator và webhook dùng chung
admin/api/zalo-admin.php     Phân quyền và bộ phân tích lệnh Zalo OA
admin/api/moodle.php         Cầu nối Moodle, đọc dữ liệu và thao tác quản trị
admin/api/moodle-client/     MoodleClient và MoodleFullClient từ bộ tool Moodle
admin/api/chat.php           Wrapper dùng backend api/chat56.php
admin/api/live-token.php     Wrapper dùng token Gemini Live
../database/                  SQL schema và hướng dẫn database
../docs/                      Mẫu cấu hình secret
```

## Xác thực và phân quyền

Dashboard phải nằm sau Directory Privacy. Backend không tin dữ liệu role gửi từ
browser; mỗi request mở kết nối MySQL, lấy username từ `REMOTE_USER`, sau đó đối
chiếu với bảng `admin_users`.

| Role | Quyền chính |
|---|---|
| `admin` | Toàn quyền, gồm user, backup/restore, học phí và cấu hình quyền Zalo |
| `training` | Hồ sơ, học tập, điểm danh; được xem tài chính; được cập nhật dữ liệu được cấp |
| `teacher` | Xem dữ liệu học vụ/sinh viên ở phạm vi giới hạn và nhập điểm danh |

Quyền Moodle được gộp vào quyền dashboard: `admin` toàn quyền, `training` được
đọc và thao tác Moodle, `teacher` chỉ được đọc.

Nếu bảng `admin_users` chưa có bản ghi, lần đăng nhập đầu tiên sau khi chạy
schema sẽ được bootstrap thành `admin`. Sau đó nên cấp quyền rõ ràng cho từng
tài khoản.

## Database

1. Tạo database MySQL, ví dụ `mtpc_students`.
2. Import `database/student_management_v2.sql` một lần trong phpMyAdmin.
   Nếu database đã được import từ trước, chạy thêm
   `database/migrations/20260831_add_student_zalo_user_id.sql` để tạo cột liên kết
   Zalo cho từng hồ sơ.
3. Tạo `/home/mtpc/private/db-config.php`:

   ```php
   <?php
   $MTPC_DB_HOST = 'localhost';
   $MTPC_DB_NAME = 'mtpc_students';
   $MTPC_DB_USER = 'DB_USER';
   $MTPC_DB_PASS = 'DB_PASSWORD';
   ```

4. Đăng nhập lại dashboard và kiểm tra mục **Hồ sơ sinh viên**.

Schema tạo các nhóm bảng chính:

```text
students, student_history, student_actions, student_documents
academic_results, semesters, subjects
attendance_sessions, attendance_records
fee_items, student_fees, fee_payments
admin_users, system_audit_logs
```

Hướng dẫn chi tiết về import nằm ở
[`../database/INSTALL-STUDENT-MANAGEMENT.md`](../database/INSTALL-STUDENT-MANAGEMENT.md).

## Bảo vệ dữ liệu sinh viên

- Database credentials và file backup phải nằm ngoài `public_html`.
- API luôn kiểm tra Directory Privacy trước khi trả dữ liệu.
- Vai trò `teacher` bị mask CCCD, điện thoại và các trường nhạy cảm theo backend.
- Ảnh sinh viên nằm ở `/home/mtpc/private/student-files`, chỉ đọc qua
  `student-file.php` sau khi xác thực.
- Mọi thay đổi quan trọng tạo record trong `student_history` và/hoặc
  `system_audit_logs`.
- Không đưa dữ liệu cá nhân, giấy tờ hoặc secret vào prompt AI nếu không cần.

## API quản trị

Các API bên dưới dùng `GET` cho đọc và `POST` cho thay đổi, trừ khi ghi chú
khác. Tất cả đều phải được gọi từ dashboard sau Directory Privacy.

### Student API

```text
GET  api/students.php?action=list
GET  api/students.php?action=get&id=...
GET  api/students.php?action=summary
GET  api/students.php?action=duplicates
POST api/students.php?action=create
POST api/students.php?action=update
```

Mỗi hồ sơ có thể lưu `zalo_user_id`. Trong mục **Hồ sơ sinh viên**, dùng nút
**Gắn Zalo học viên** để nhập ID lấy từ nhật ký tin nhắn OA. Có thể dùng nút
**Thông báo Zalo** để lọc theo lớp/trạng thái/mã hoặc tên rồi gửi một nội dung
riêng đến từng tài khoản đã liên kết. Nội dung hỗ trợ các biến
`{{full_name}}`, `{{student_code}}`, `{{class_name}}`, `{{program_name}}`,
`{{cohort}}`, `{{status}}`.

### Student operations

`student-ops.php` cung cấp overview, attendance sessions/roster/alerts, fees,
academic results, student history/actions/documents, user list, audit, export,
backup và restore. Các thao tác ghi như tạo buổi điểm danh, ghi nhận điểm danh,
gán học phí, nhận thanh toán, cập nhật điểm và thêm tài liệu đều kiểm tra role.

### Zalo OA

```text
GET  api/zalo-oa.php?action=messages&date_mode=today
GET  api/zalo-oa.php?action=operators
GET  api/zalo-oa.php?action=groups
GET  api/zalo-oa.php?action=group-info&group_id=...
GET  api/zalo-oa.php?action=group-members&group_id=...
GET  api/zalo-oa.php?action=group-conversation&group_id=...
POST api/zalo-oa.php?action=send
POST api/zalo-oa.php?action=send-student-notifications
POST api/zalo-oa.php?action=operator-upsert
POST api/zalo-oa.php?action=operator-link-request
POST api/zalo-oa.php?action=group-create
POST api/zalo-oa.php?action=group-register
POST api/zalo-oa.php?action=group-update
POST api/zalo-oa.php?action=group-send
POST api/zalo-oa.php?action=group-accept-members
```

Webhook public của OA dùng cùng implementation nhưng đi qua
`https://agent.mtpc.edu.vn/api/zalo-oa.php?action=webhook&token=...`. Các file
message/operator/pending command được lưu ngoài web root tại:

Người điều khiển Zalo có quyền phù hợp có thể nhắn, ví dụ: `thông báo lớp
CNTT-K26: Ngày mai học bù tiết 1`. Nhi sẽ báo số học viên đã liên kết và chờ
`XÁC NHẬN` trước khi gửi riêng từng tài khoản; học viên chưa có
`zalo_user_id` sẽ được bỏ qua.

```text
/home/mtpc/private/mtpc-zalo-oa/messages.jsonl
/home/mtpc/private/mtpc-zalo-oa/operators.json
/home/mtpc/private/mtpc-zalo-oa/groups.json
/home/mtpc/private/mtpc-zalo-oa/pending-commands.json
/home/mtpc/private/mtpc-zalo-oa/link-requests.json
```

### Email

`api/email.php` đọc danh sách email và nội dung qua IMAP, đồng thời gửi thư qua
SMTP. Cấu hình nằm ở `/home/mtpc/private/email-config.php`; mẫu có tại
[`../docs/email-config.example.php`](../docs/email-config.example.php). Nên dùng
mật khẩu ứng dụng Gmail thay vì mật khẩu tài khoản chính.

## Điều khiển quản trị qua Zalo OA

### Cấp quyền

Trong dashboard mở **Đăng nhập & phân quyền → Quản trị qua Zalo OA**. Có hai
cách:

1. Nhập trực tiếp Zalo User ID lấy từ nhật ký tin nhắn.
2. Nhập số điện thoại, chọn role rồi tạo mã liên kết. Người dùng nhắn mã đó
   cho OA theo dạng `KẾT NỐI 123456`.

Zalo User ID được lưu trong `operators.json`, không dựa vào số điện thoại khi
thực thi lệnh. Số điện thoại chỉ dùng cho bước liên kết ban đầu.

### Lệnh hỗ trợ

Ví dụ:

```text
xem tổng số sinh viên
tìm sinh viên Nguyễn Nhật Tiến
xem hồ sơ MTPC001
xem cảnh báo điểm danh
xem tổng quan học phí
```

AI chỉ phân tích ý định thành allowlist cố định; không cho phép Gemini tự tạo
SQL hoặc tự gọi endpoint tùy ý. Lệnh đọc được thực hiện ngay nếu role có
permission tương ứng.

Lệnh thay đổi như đổi trạng thái hoặc chuyển lớp sẽ:

1. Tìm đúng sinh viên trong database.
2. Gửi bản tóm tắt giá trị cũ và mới về Zalo.
3. Chờ `XÁC NHẬN` hoặc `HỦY` trong 10 phút.
4. Ghi database transaction, lịch sử sinh viên và audit log.

Auto-reply thông thường trên OA không cần bấm duyệt trên web. Bước xác nhận
chỉ áp dụng cho thay đổi dữ liệu quản trị để tránh AI hiểu nhầm câu lệnh.

### Nhóm Zalo OA (GMF)

Trong **Đăng nhập & phân quyền → Nhóm Zalo OA (GMF)**:

1. Chọn **Tạo nhóm GMF**, nhập tên nhóm, `asset_id` của gói GMF và các Zalo
   User ID thành viên ban đầu.
2. Nếu nhóm đã tạo trong OA Manager, chọn **Kết nối nhóm có sẵn** rồi nhập
   `group_id`.
3. Bấm **Quản lý** để cập nhật tên/mô tả/cài đặt, xem thành viên, đọc hội
   thoại, duyệt thành viên mới hoặc gửi tin vào nhóm.

Nhóm phải thuộc OA và ứng dụng phải được cấp quyền **Quản lý thông tin nhóm**.
API dùng các endpoint GMF của Zalo, không gửi vào nhóm Zalo cá nhân thông
thường. Có thể xem tài liệu gửi tin nhóm tại
<https://stc-developers.zdn.vn/docs/v2/official-account/nhom-chat-gmf/tin-nhan/text_message>.

Tài khoản Zalo đã được cấp quyền trong **Quản trị qua Zalo OA** dùng chung
danh sách nhóm này. Các lệnh đọc như `xem nhóm`, `xem thành viên nhóm <tên hoặc
group_id>` và `xem tin nhắn nhóm <tên hoặc group_id>` được trả lời ngay trên
Zalo. Các lệnh thay đổi như `gửi tin vào nhóm ...` hoặc `đổi tên nhóm ...`
được giữ ở trạng thái chờ và chỉ thực hiện sau khi người điều khiển nhắn
`XÁC NHẬN`. Web Admin vẫn là kênh quản lý trực tiếp tương ứng.

Để gửi tin nhắn riêng tự động, quản trị viên hoặc tài khoản vai trò phòng đào
tạo có thể nhắn theo mẫu `nhắn riêng đến <Zalo User ID>: <nội dung>`. Tin này
được gửi ngay qua OA và được lưu vào nhật ký để Web Admin xem lại; User ID phải
là ID số đã lấy từ tin nhắn Zalo OA.

## Cấu hình các dịch vụ

Các file secret cần có trên hosting:

```text
/home/mtpc/private/db-config.php
/home/mtpc/private/gemini-config.php
/home/mtpc/private/email-config.php
/home/mtpc/private/zalo-oa-config.php
/home/mtpc/private/moodle-config.php
```

Ví dụ Gemini:

```php
<?php
$GEMINI_API_KEY = 'GEMINI_API_KEY';
```

Không commit các file trên vào GitHub. Các file mẫu trong `docs/` chỉ chứa
placeholder.

### Moodle

Trong Moodle, bật **Site administration → Advanced features → Enable web
services**, bật **REST protocol**, tạo External service có các hàm cần dùng và
tạo token cho một service account riêng. Không dùng token admin cá nhân.

Tạo `/home/mtpc/private/moodle-config.php` theo mẫu
[`../docs/moodle-config.example.php`](../docs/moodle-config.example.php):

```php
<?php
return array(
    'moodle_url' => 'https://moodle.mtpc.edu.vn',
    'moodle_token' => 'MOODLE_WEBSERVICE_TOKEN',
);
```

Sau khi deploy, vào mục **Moodle** trong dashboard. Dashboard sẽ kiểm tra site
info và các Web Service functions trước khi hiển thị dữ liệu. Các thao tác tạo
khoá học, ghi danh và xoá dữ liệu chỉ chạy qua API server-side, có kiểm tra role;
xoá khoá học/tài khoản còn yêu cầu xác nhận `DELETE` ở backend.

## Triển khai

Từ repository hiện tại, `.cpanel.yml` copy:

```text
admin/* -> /home/mtpc/public_html/admin/
```

Phần `agent` được copy riêng:

```text
index.html + api/* -> /home/mtpc/public_html/agent/
```

Quy trình:

1. Review diff và chạy PHP lint.
2. Commit/push GitHub.
3. Vào **cPanel → Git Version Control → Pull or Deploy**.
4. Kiểm tra Directory Privacy, database config và error log.
5. Đăng nhập dashboard, mở lại các view quan trọng và thử một API đọc.

## Kiểm tra trước khi push

```bash
php -l admin/api/_student_bootstrap.php
php -l admin/api/students.php
php -l admin/api/student-ops.php
php -l admin/api/student-import.php
php -l admin/api/email.php
php -l admin/api/zalo-oa.php
php -l admin/api/zalo-admin.php
php -l admin/api/moodle.php
php -l admin/api/moodle-client/MoodleClient.php
php -l admin/api/moodle-client/MoodleFullClient.php
git diff --check
```

## Xử lý lỗi thường gặp

- **401 Directory Privacy:** kiểm tra domain đã bật bảo vệ và browser đã đăng nhập.
- **403 chưa được cấp quyền:** kiểm tra username trong `admin_users`, role và
  status `active`.
- **503 chưa chạy database:** kiểm tra import schema và `db-config.php`.
- **Zalo nhận tin nhưng không trả lời:** kiểm tra access token, webhook token,
  `$MTPC_ZALO_OA_AUTO_REPLY = true` và PHP error log.
- **Tên Zalo trống:** OA cần quyền quản lý thông tin người dùng; sau khi cấp
  quyền nên tạo access token mới rồi gửi một tin nhắn mới.
- **Email không đọc được:** bật PHP IMAP, kiểm tra Gmail App Password, cổng
  IMAP 993 và chứng chỉ CA trên hosting.
- **Ảnh không hiển thị:** kiểm tra file còn ở `private/student-files` và request
  có đi qua Directory Privacy.

## License và dữ liệu

Đây là phần mềm nội bộ. Không chia sẻ source cùng secret, database dump, ảnh,
email, Zalo message log hoặc backup có dữ liệu cá nhân.
