# MTPC Modern Moodle theme

Boost child theme for the MTPC eLearning site. It changes presentation only and does not override Moodle core templates or store user data.

After deployment, visit **Site administration → Notifications**, then select **MTPC Modern** in **Appearance → Theme selector** and purge theme caches.

## Orb quản trị trong Moodle

Theme tự tải Orb Nhi ở góc phải dưới cho tài khoản Moodle đã đăng nhập. Với học
sinh, Orb chỉ tra cứu các khóa đã ghi danh, bài học, bài tập, bài kiểm tra, điểm,
tiến độ, thông báo và lịch của chính học sinh; không có quyền tạo/sửa/xóa, ghi
danh, chấm điểm hoặc gửi tin. Tài khoản có `moodle/site:config` mới nhận được
Orb quản trị đầy đủ. Orb gửi câu hỏi tới `local/mtpcbridge/moodle-orb.php`;
Gemini key và Moodle service token vẫn chỉ nằm ở máy chủ.

Sau khi deploy, hoàn tất nâng cấp plugin ở **Site administration →
Notifications**, purge toàn bộ cache rồi tải lại Moodle bằng **Ctrl + F5**.
