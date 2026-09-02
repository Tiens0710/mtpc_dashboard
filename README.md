# MTPC Agent

Trợ lý AI hướng giọng nói cho website của Trường Trung cấp Miền Tây, chạy tại
`https://agent.mtpc.edu.vn`.

Trong source hiện tại, phần Agent nằm ở thư mục gốc (`index.html` và `api/`).
Dashboard quản trị nằm trong `admin/` và có tài liệu riêng tại
[`admin/README.md`](admin/README.md). Đây là một monorepo, nhưng `.cpanel.yml`
triển khai hai phần ra hai document root khác nhau.

## Chức năng

- Trợ lý tuyển sinh Nhi giao tiếp bằng giọng nói qua Gemini Live.
- Nhận dạng giọng nói và hiển thị transcript người dùng/trợ lý.
- Cho phép nhập câu hỏi bằng văn bản khi không dùng microphone.
- Đọc nội dung trả lời bằng Gemini TTS.
- Điều khiển các khu vực trên website bằng function calling: mở ngành, đọc
  nội dung, tìm kiếm, xem học phí, checklist hồ sơ và liên hệ tư vấn.
- RAG từ nội dung công khai trên `https://mtpc.edu.vn`.
- Nhận tin nhắn từ Zalo Official Account, tự trả lời 1:1 bằng tiếng Việt với
  tư cách Trường Trung cấp Miền Tây.
- Cho phép tài khoản Zalo đã được cấp quyền gửi lệnh quản trị trực tiếp cho OA.
- MTPC Admin kết nối `moodle.mtpc.edu.vn` qua Moodle Web Services để quản lý
  khoá học, tài khoản và ghi danh.

## Kiến trúc kỹ thuật

| Thành phần | Công nghệ / mô hình |
|---|---|
| Giao diện | HTML, CSS và JavaScript thuần, không cần bundler |
| Text assistant | PHP gateway gọi Gemini `gemini-3.1-flash-lite` |
| Voice assistant | Gemini Live qua WebSocket từ trình duyệt |
| Live token | PHP tạo ephemeral token, không đưa API key vào browser |
| Text-to-speech | Gemini `gemini-3.1-flash-tts-preview` |
| Knowledge/RAG | Crawler PHP, JSON chunks và inverted index |
| Zalo OA | Zalo OA Webhook + OA Send Message API |
| Moodle | Moodle Web Services REST API, PHP/cURL và service account token |
| Hosting | cPanel, Apache/LiteSpeed, PHP 5.6-compatible |

API key Gemini chỉ được đọc từ biến môi trường `GEMINI_API_KEY` hoặc file
`/home/mtpc/private/gemini-config.php`. Browser chỉ nhận token phiên Gemini
ngắn hạn từ server.

## Cấu trúc thư mục

```text
index.html                 Giao diện Agent public
api/chat56.php             Text chat + RAG; file deploy thành api/chat.php
api/live-token56.php       Cấp token Gemini Live; deploy thành api/live-token.php
api/tts56.php              Gateway Gemini TTS; deploy thành api/tts.php
api/zalo-oa.php             Entrypoint webhook public của Zalo OA
admin/                      MTPC Admin và các API quản trị
database/                   SQL schema và hướng dẫn database
docs/                       Mẫu cấu hình private, không chứa secret thật
tools/sync-knowledge.php   Đồng bộ dữ liệu mtpc.edu.vn vào kho RAG
.cpanel.yml                Cấu hình triển khai cPanel
```

## API của Agent

### Text chat

```text
POST /api/chat.php
Content-Type: application/json
```

Body tối thiểu:

```json
{
  "messages": [
    {"role": "user", "text": "Trường có những ngành nào?"}
  ]
}
```

Response gồm `text`, `sources`, `knowledge_used` và `model`. Server lấy tối đa
12 message gần nhất, tìm các đoạn kiến thức liên quan rồi gửi context cho
Gemini. Nếu không có nguồn phù hợp, AI phải nói rõ và hướng người dùng liên hệ
nhà trường thay vì tự bịa thông tin.

### Gemini Live

```text
POST /api/live-token.php
```

Server trả token phiên ngắn hạn và model Live. Trình duyệt dùng token này để
mở WebSocket tới Gemini, truyền audio PCM và nhận audio PCM/transcript. API key
chính không bao giờ được nhúng vào `index.html`.

### Gemini TTS

```text
POST /api/tts.php
Content-Type: application/json
```

Body:

```json
{"text":"Nội dung cần đọc"}
```

Endpoint có giới hạn tần suất theo IP và giới hạn độ dài văn bản để tránh lạm
dụng chi phí API.

## Zalo Official Account

### Webhook

Webhook phải trỏ tới:

```text
https://agent.mtpc.edu.vn/api/zalo-oa.php?action=webhook&token=WEBHOOK_TOKEN
```

Zalo gửi sự kiện bằng HTTP `POST`. Endpoint xác thực token, lưu message vào
vùng private, trả HTTP 200 nhanh cho Zalo và xử lý auto-reply sau đó khi
FastCGI hỗ trợ xử lý nền.

File public `api/zalo-oa.php` chỉ là wrapper; implementation dùng chung nằm ở
`admin/api/zalo-oa.php` để dashboard có thể đọc và gửi tin từ cùng một kho dữ
liệu.

### Cấu hình private

Tạo file `/home/mtpc/private/zalo-oa-config.php` từ
[`docs/zalo-oa-config.example.php`](docs/zalo-oa-config.example.php):

```php
<?php
$MTPC_ZALO_OA_ACCESS_TOKEN = 'ZALO_OA_ACCESS_TOKEN';
$MTPC_ZALO_OA_WEBHOOK_TOKEN = 'LONG_RANDOM_TOKEN';
$MTPC_ZALO_OA_ID = 'OA_ID';
$MTPC_ZALO_OA_AUTO_REPLY = true;
$MTPC_ZALO_OA_SEND_URL = 'https://openapi.zalo.me/v3.0/oa/message/cs';
$MTPC_ZALO_OA_GROUP_API_BASE = 'https://openapi.zalo.me/v3.0/oa/group';
$MTPC_ZALO_OA_PROFILE_URL = 'https://openapi.zalo.me/v3.0/oa/user/detail';
$MTPC_ZALO_OA_PROFILE_FALLBACK_URL = 'https://openapi.zalo.me/v2.0/oa/getprofile';
```

Không commit access token, refresh token, webhook token hoặc Gemini API key.
Thư mục `private/` trong repository đã được `.gitignore` loại khỏi Git.

## Moodle Admin

Mã tool Moodle ở `D:/moodle` đã được đưa vào `admin/api/moodle-client/`. Dashboard
không gọi Moodle trực tiếp từ browser: `admin/api/moodle.php` giữ token ở phía
server và cung cấp các thao tác kiểm tra kết nối, đọc khoá học/nội dung/thành
viên, tìm tài khoản, đăng thông báo, xem bài nộp/điểm, quản lý nhóm, tạo lịch,
gửi tin nhắn, tạo khoá học và ghi danh.

Tạo file `/home/mtpc/private/moodle-config.php` theo mẫu
[`docs/moodle-config.example.php`](docs/moodle-config.example.php), sau đó vào
mục **Moodle** trong Admin. Quyền được áp dụng theo role dashboard: `admin`
toàn quyền, `training` đọc/ghi Moodle và `teacher` được cấp các quyền giảng dạy
theo nhóm chức năng. Chi tiết cấu hình
Moodle Web Services nằm trong [`admin/README.md`](admin/README.md).

Trong **AI Copilot** và orb Nhi, quản trị viên có thể hỏi trực tiếp về Moodle
(trạng thái kết nối, khoá học, nội dung, thành viên, tài khoản và bài tập).
Orb Nhi còn hỗ trợ đăng thông báo lên forum Announcements, xem bài nộp/điểm,
quản lý nhóm, tạo lịch, gửi tin Moodle và đăng bài giảng dạng Page hoặc liên kết
video/tài liệu. Kết quả được hiển thị thành thẻ trực quan; mọi thao tác ghi đều
được đưa vào **Phê duyệt AI** trước khi thực hiện.

Để đăng bài giảng dạng Page/URL hoặc upload file từ máy, cần cài plugin Moodle tại
[`moodle-plugin/local/mtpcbridge`](moodle-plugin/local/mtpcbridge) vào thư mục
`local/` của Moodle, rồi tạo/cập nhật External service để thêm function
`local_mtpcbridge_create_lecture` và `local_mtpcbridge_create_file_lecture` cho
token đang dùng.

Trong Admin, mở mục **Moodle → Đăng bài giảng**, chọn Course, Category/chủ đề,
chọn file rồi bấm **Đăng bài giảng**. File được tạo thành hoạt động Page trong
đúng section; hỗ trợ PDF, Word, PowerPoint, TXT/Markdown/HTML và JPG/PNG, tối
đa 20 MB. Khi nói “đăng bài giảng” với orb Nhi mà chưa cung cấp đủ Course và
nội dung, dashboard sẽ mở form này để chọn nhanh.

### Lấy đúng tên người dùng

Webhook thường chỉ gửi `sender.id`. Backend sẽ gọi API hồ sơ OA và đọc lần
lượt `display_name`, `user_display_name`, `name`, `user_alias` và
`shared_info.name`. Tên được lưu vào message log và đồng bộ vào hồ sơ operator
nếu người đó có quyền quản trị.

Để gọi được API hồ sơ, ứng dụng Zalo OA cần quyền quản lý thông tin người dùng.
Nếu thiếu quyền hoặc token cũ, hệ thống vẫn lưu và hiển thị `user_id` thay vì
đoán tên.

### Lệnh quản trị qua Zalo OA

Tài khoản phải được cấp quyền trong mục **Đăng nhập & phân quyền → Quản trị qua
Zalo OA**. Có thể liên kết bằng mã sinh từ số điện thoại hoặc nhập Zalo User ID.

Ví dụ lệnh đọc:

```text
xem tổng số sinh viên
tìm sinh viên Nguyễn Nhật Tiến
xem hồ sơ MTPC001
xem cảnh báo điểm danh
xem tổng quan học phí
```

Lệnh thay đổi dữ liệu tạo bản tóm tắt và yêu cầu nhắn `XÁC NHẬN` hoặc `HỦY`
trong vòng 10 phút. Việc xác nhận diễn ra ngay trong cuộc trò chuyện Zalo,
không cần mở website. Tất cả thao tác thay đổi đều ghi vào lịch sử sinh viên
và audit log.

Quản trị viên cũng có thể yêu cầu orb Nhi tạo nhóm Zalo GMF bằng giọng nói.
Nhi sẽ hỏi các thông tin còn thiếu như tên nhóm, `asset_id` gói GMF và các Zalo
User ID muốn thêm ban đầu, sau đó gọi API tạo nhóm và trả về Group ID/link.

## Đồng bộ knowledge/RAG

`tools/sync-knowledge.php` đọc sitemap và các trang public của `mtpc.edu.vn`,
loại bỏ phần giao diện, chia nội dung thành chunks rồi tạo:

```text
/home/mtpc/private/mtpc-knowledge/pages.json
/home/mtpc/private/mtpc-knowledge/chunks.json
/home/mtpc/private/mtpc-knowledge/index.json
/home/mtpc/private/mtpc-knowledge/status.json
```

Chạy thủ công trên hosting:

```bash
php /path/to/sync-knowledge.php
php /path/to/sync-knowledge.php --full
```

Nên đặt cron sau khi nội dung website thay đổi. Script chỉ đọc các URL cùng
domain `mtpc.edu.vn` và ghi output ra ngoài `public_html`.

## Triển khai cPanel

`.cpanel.yml` thực hiện các bước chính:

```text
index.html + api/*  -> /home/mtpc/public_html/agent/
admin/*             -> /home/mtpc/public_html/admin/
```

Sau khi push GitHub, vào **cPanel → Git Version Control → Pull or Deploy** để
pull/deploy commit mới. Kiểm tra:

```text
https://agent.mtpc.edu.vn
https://agent.mtpc.edu.vn/api/live-token.php   (POST)
https://agent.mtpc.edu.vn/api/zalo-oa.php?action=webhook&token=...
```

Không truy cập các file trong `/home/mtpc/private` qua web. Các file cấu hình
private cần được tạo thủ công trên hosting hoặc qua secret manager của hosting.

## Kiểm tra trước khi deploy

```bash
php -l api/chat56.php
php -l api/live-token56.php
php -l api/tts56.php
php -l api/zalo-oa.php
php -l admin/api/zalo-oa.php
php -l admin/api/zalo-admin.php
git diff --check
```

## Xử lý lỗi thường gặp

- **Live không kết nối:** kiểm tra `GEMINI_API_KEY`, endpoint `live-token.php`,
  thời hạn token và console WebSocket của trình duyệt.
- **Text không trả lời:** kiểm tra API key, PHP cURL và file knowledge trong
  `/home/mtpc/private/mtpc-knowledge`.
- **Zalo không nhận webhook:** kiểm tra URL có `action=webhook`, token giống
  cấu hình private, endpoint trả HTTP 200 và Zalo OA đã đăng ký đúng sự kiện
  nhận tin nhắn.
- **Zalo không tự trả lời:** kiểm tra `$MTPC_ZALO_OA_AUTO_REPLY = true`,
  access token còn hạn, quyền gửi tin và error log PHP.
- **Không hiện tên:** cấp quyền quản lý thông tin người dùng cho app/OA và
  cấp lại access token; người dùng cũng cần đã tương tác với OA.

## License và dữ liệu

Đây là mã nguồn nội bộ của Trường Trung cấp Miền Tây. Không công khai API key,
thông tin database, dữ liệu sinh viên, message log hoặc file backup.
