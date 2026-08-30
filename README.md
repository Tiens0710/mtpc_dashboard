# mtpc_dashboard

Dashboard quản trị và trợ lý AI cho Trường Trung cấp Miền Tây.

## Thư mục chính

- `index.html`: website MTPC.
- `admin/`: dashboard quản trị và AI Copilot.
- `api/`: các API PHP tương thích hosting cPanel/PHP 5.6.
- `.cpanel.yml`: cấu hình deploy lên cPanel.

## Kết nối Zalo OA

API cầu nối nằm tại `admin/api/zalo-oa.php`. Webhook nhận tin nhắn ở:

`https://agent.mtpc.edu.vn/api/zalo-oa.php?action=webhook&token=WEBHOOK_TOKEN`

Tạo file `/home/mtpc/private/zalo-oa-config.php` trên hosting theo mẫu
`docs/zalo-oa-config.example.php`. Không đưa access token vào HTML hoặc GitHub.

Sau khi cấu hình, Zalo OA có thể tự trả lời 1:1 bằng `gemini-3.1-flash-lite` với tư cách Trường Trung cấp Miền Tây. Trên dashboard web, Nhi vẫn dùng Gemini Live; lệnh “đọc tin nhắn Zalo hôm nay” sẽ đọc các tin đã lưu trong ngày. Lệnh trả lời Zalo từ dashboard gửi trực tiếp qua OA, không đi qua hàng chờ phê duyệt.

Để hiện tên người dùng thay cho `user_id`, ứng dụng Zalo cần được cấp quyền quản lý thông tin người dùng. Nếu OA chưa có quyền này, dashboard vẫn hiện `user_id` và không tự đoán tên.
