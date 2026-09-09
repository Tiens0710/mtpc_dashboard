# Lớp học trực tuyến và chấm bài AI

## Google Meet → Moodle → Zalo

1. Bật Google Calendar API trong Google Cloud.
2. Tạo service account và khóa JSON.
3. Chia sẻ lịch Google dùng cho nhà trường với email service account, quyền sửa sự kiện.
4. Sao chép `docs/google-calendar-config.example.php` thành
   `/home/mtpc/private/google-calendar-config.php` và điền thông tin thật.
5. Nếu Google Workspace yêu cầu tạo Meet thay mặt giáo viên, bật domain-wide
   delegation và đặt `impersonate_user` là email giáo viên/đơn vị được ủy quyền.
6. Bảo đảm hồ sơ học viên có email trùng với tài khoản Moodle và đã gắn
   `zalo_user_id`.

Trong Orb quản trị, giáo viên có thể nói:

> Tạo lớp Google Meet môn Lập trình Python cơ bản lúc 19:00 ngày 10/09/2026,
> thời lượng 90 phút.

Hệ thống sẽ tạo một Meet riêng, ghi sự kiện vào lịch khóa học Moodle, gửi tin
Moodle cho học viên đã ghi danh và gửi Zalo cho các hồ sơ khớp email/mã sinh viên.
Kết quả trả về có số lượng đã gửi, bỏ qua và lỗi để không báo thành công giả.

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
