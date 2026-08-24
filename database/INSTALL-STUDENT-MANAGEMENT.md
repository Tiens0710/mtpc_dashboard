# Cài đặt quản lý sinh viên MTPC

1. Mở phpMyAdmin, chọn database `mtpc_students`.
2. Chọn **Import** và chạy `student_management_v2.sql` đúng một lần. File này mở rộng bảng `students`, vì vậy không chạy lại sau khi đã thành công.
3. Deploy commit mới trong **cPanel Git Version Control → Pull or Deploy**.
4. Đăng nhập `admin.mtpc.edu.vn` bằng tài khoản Directory Privacy hiện tại. Tài khoản đầu tiên truy cập sau migration tự nhận vai trò `admin`.
5. Mở **Đăng nhập & phân quyền** để thêm username của Phòng đào tạo hoặc Giáo viên. Username phải tồn tại trong Directory Privacy.

## Phân quyền

- `admin`: toàn quyền, gồm học phí, biên lai, tài khoản, sao lưu và khôi phục.
- `training`: hồ sơ, học tập, điểm danh; chỉ được xem tài chính.
- `teacher`: xem dữ liệu học vụ đã giới hạn và nhập điểm danh.

## Import Excel

Xuất file CSV mẫu từ dashboard, chỉnh bằng Excel rồi lưu lại dạng **CSV UTF-8**. Import tối đa 500 dòng/lần. Các cột bắt buộc là `student_code` và `full_name`.

## An toàn dữ liệu

- Dashboard phải được bảo vệ bằng Directory Privacy và HTTPS.
- AI chỉ nhận mã sinh viên, họ tên, ngành, lớp, khóa, trạng thái và dữ liệu học vụ cần thiết; không nhận CCCD, địa chỉ, điện thoại, email, người liên hệ hoặc giấy tờ.
- File backup chứa dữ liệu cá nhân. Chỉ tải và lưu trong vùng được bảo vệ.
