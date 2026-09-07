# MTPC Modern Moodle theme

Boost child theme for the MTPC eLearning site. It changes presentation only and does not override Moodle core templates or store user data.

After deployment, visit **Site administration → Notifications**, then select **MTPC Modern** in **Appearance → Theme selector** and purge theme caches.

## Orb trong Moodle

Theme tự tải Orb Nhi ở góc phải dưới cho tài khoản Moodle đã đăng nhập. Orb là
điểm điều khiển chính: người dùng bấm vào quả cầu để nói, còn khung hội thoại
chỉ mở khi cần xem lại lịch sử hoặc nhập văn bản. Trình duyệt sẽ xin quyền
microphone và dùng nhận diện giọng nói tiếng Việt; nếu trình duyệt không hỗ trợ,
người dùng vẫn có thể nhập văn bản trong cùng cuộc trò chuyện. Với học
sinh, Orb chỉ tra cứu các khóa đã ghi danh, bài học, bài tập, bài kiểm tra, điểm,
tiến độ, thông báo và lịch của chính học sinh; không có quyền tạo/sửa/xóa, ghi
danh, chấm điểm hoặc gửi tin. Tài khoản có `moodle/site:config` mới nhận được
Orb quản trị đầy đủ. Orb gửi câu hỏi tới `local/mtpcbridge/moodle-orb.php`;
Gemini key và Moodle service token vẫn chỉ nằm ở máy chủ.

Sau khi deploy, hoàn tất nâng cấp plugin ở **Site administration →
Notifications**, purge toàn bộ cache rồi tải lại Moodle bằng **Ctrl + F5**.
