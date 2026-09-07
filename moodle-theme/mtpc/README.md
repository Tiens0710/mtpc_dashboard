# MTPC Modern Moodle theme

Boost child theme for the MTPC eLearning site. It changes presentation only and does not override Moodle core templates or store user data.

After deployment, visit **Site administration → Notifications**, then select **MTPC Modern** in **Appearance → Theme selector** and purge theme caches.

## Orb quản trị trong Moodle

Theme tự tải Orb Nhi ở góc phải dưới cho tài khoản có quyền
`moodle/site:config`. Orb gửi câu hỏi tới `local/mtpcbridge/moodle-orb.php`;
Gemini key và Moodle service token vẫn chỉ nằm ở máy chủ. Các thao tác đọc được
thực hiện ngay, còn tạo/sửa/xóa, ghi danh, chấm điểm hoặc gửi tin luôn yêu cầu
nhấn **Xác nhận** trước khi chạy.

Sau khi deploy, hoàn tất nâng cấp plugin ở **Site administration →
Notifications**, purge toàn bộ cache rồi tải lại Moodle bằng **Ctrl + F5**.
