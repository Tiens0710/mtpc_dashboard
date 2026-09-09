# Lớp học trực tuyến và chấm bài AI

## Link Google Meet của giáo viên → Moodle → Zalo

Điều kiện duy nhất cho luồng mặc định là hồ sơ học viên có email/mã sinh viên
trùng với tài khoản Moodle và đã gắn `zalo_user_id`.

Luồng mặc định là giáo viên môn học tự tạo Meet bằng tài khoản Google của mình,
sau đó dán link vào Orb quản trị. Tài khoản vai trò giáo viên chỉ được đăng link
cho khóa mà username Moodle tương ứng đang có vai trò `teacher`,
`editingteacher` hoặc `manager`.

Trong Orb quản trị, giáo viên có thể nói:

> Lên lịch học môn Lập trình Python cơ bản lúc 19:00 ngày 10/09/2026, thời lượng
> 90 phút, link https://meet.google.com/abc-defg-hij.

Hệ thống sẽ kiểm tra link `meet.google.com`, ghi sự kiện vào lịch khóa học Moodle, gửi tin
Moodle cho học viên đã ghi danh và gửi Zalo cho các hồ sơ khớp email/mã sinh viên.
Kết quả trả về có số lượng đã gửi, bỏ qua và lỗi để không báo thành công giả.
Plugin đồng thời xếp một tác vụ nhắc trước giờ học 15 phút. Tác vụ tạo thông báo
Moodle cho học viên và thông báo đó tiếp tục được chuyển qua Zalo OA của trường.

Khả năng tự tạo Meet qua Google Calendar API vẫn còn làm phương án dự phòng cho
quản trị/đào tạo, nhưng Orb sẽ ưu tiên yêu cầu link do giáo viên cung cấp. Chỉ
khi muốn dùng phương án dự phòng này mới cần bật Google Calendar API, tạo service
account và cấu hình `/home/mtpc/private/google-calendar-config.php` theo file mẫu.

## Tự động chuyển tiếp thông báo khóa học qua Zalo OA

Plugin theo dõi các thông báo Moodle có gắn với khóa học. Nếu người nhận đang
được ghi danh với vai trò học viên và hồ sơ được liên kết bằng `idnumber`/mã sinh
viên hoặc email, thông báo sẽ được đưa vào hàng đợi và gửi bằng Zalo OA của
trường. Hàng đợi lưu `notificationid` duy nhất để tránh gửi trùng.

Tác vụ này phản chiếu những thông báo Moodle thực sự phát sinh (ví dụ thông báo
diễn đàn, bài tập, điểm và lời nhắc do module hỗ trợ), không tự tạo thông báo cho
những thay đổi mà Moodle vốn không thông báo. Có thể bật/tắt tại **Site
administration → Plugins → Local plugins → MTPC teaching bridge**. Cron Moodle
cần chạy ít nhất mỗi phút để tác vụ nền được gửi sớm.

## Chấm bài AI có giáo viên duyệt

Trong Orb quản trị, giáo viên có thể nói:

> Chấm nháp bài tập Python vòng lặp theo rubric: đúng thuật toán 5 điểm, kết quả
> 3 điểm, trình bày 2 điểm; thang điểm 10.

AI chỉ chấm các bài nộp bằng văn bản trực tuyến và trả về điểm, nhận xét, dẫn
chứng ngắn cùng độ tin cậy. Kết quả chưa được ghi vào sổ điểm. Giáo viên cần xem
lại rồi yêu cầu Orb lưu danh sách điểm. Bài nộp dạng file được đánh dấu để giáo
viên xử lý riêng, không bị chấm đoán.
