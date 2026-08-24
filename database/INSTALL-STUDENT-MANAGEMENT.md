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

## Import Excel kèm hình ảnh

Trong **Hồ sơ sinh viên**, chọn **Nhập Excel + ảnh**:

1. Tải file mẫu từ dashboard và điền dữ liệu. Hỗ trợ `.xlsx` (sheet đầu tiên) hoặc `.csv` UTF-8, tối đa 1.000 dòng.
2. Đặt tên ảnh theo mã sinh viên, ví dụ `MTPC001.jpg`, `MTPC002.png`.
3. Chọn nhiều ảnh trực tiếp hoặc nén ảnh thành một file ZIP.
4. Chọn loại ảnh: **Ảnh chân dung** hoặc **Ảnh giấy tờ/hồ sơ**.
5. Kiểm tra màn hình xem trước rồi mới xác nhận nhập.

Hệ thống kiểm tra mã sinh viên, CCCD, email và số điện thoại bị trùng. Ảnh được lưu tại `/home/mtpc/private/student-files`, không nằm trong `public_html`; ảnh chân dung chỉ được đọc qua API sau khi đăng nhập Directory Privacy.

Nếu hosting chưa bật `ZipArchive`, hãy dùng `.csv` và chọn nhiều ảnh trực tiếp. Giới hạn tải lên thực tế phụ thuộc `upload_max_filesize`, `post_max_size` và `max_file_uploads` trong PHP Selector.

## An toàn dữ liệu

- Dashboard phải được bảo vệ bằng Directory Privacy và HTTPS.
- AI chỉ nhận mã sinh viên, họ tên, ngành, lớp, khóa, trạng thái và dữ liệu học vụ cần thiết; không nhận CCCD, địa chỉ, điện thoại, email, người liên hệ hoặc giấy tờ.
- File backup chứa dữ liệu cá nhân. Chỉ tải và lưu trong vùng được bảo vệ.
