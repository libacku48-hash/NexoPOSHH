# Thanh toán VietQR và tiền tệ VND

> Nhánh `viet-hoa`, repo `libacku48-hash/NexoPOSHH`. Tính năng: preset tiền tệ
> VND + hình thức thanh toán VietQR (QR chuyển khoản, nhân viên xác nhận tay).
> **VNPay tự động xác nhận chưa làm** — xem mục 5.

## 1. Tiền tệ VND

### Cấu hình

Dashboard → Cài đặt → Cài đặt chung → tab **Tiền tệ** → **Currency Preset**,
chọn **Vietnamese Dong (VND)** rồi lưu. Hệ thống tự điền:

| Mục | Giá trị khi chọn VND |
|---|---|
| ISO | `VND` |
| Ký hiệu | `₫` |
| Vị trí | Sau số tiền (`1.000.000 ₫`) |
| Hiển thị | Ký hiệu (`₫`, không phải mã `VND`) |
| Số lẻ | 0 (VND không có xu lẻ) |
| Phân tách hàng nghìn | `.` |
| Phân tách thập phân | `,` |

Chọn preset **US Dollar (USD)** để trở lại đô la. Chọn **Custom** để chỉnh
từng mục tay — preset chỉ áp dụng khi bạn đổi lựa chọn preset, nên chỉnh tay
sau đó không bị ghi đè.

Định dạng tiền áp dụng cho toàn bộ nơi hiển thị tiền: POS, hóa đơn, báo cáo
(đi qua `CurrencyService` và bộ lọc `nsCurrency` ở frontend, đều đọc cùng
các option `ns_currency_*`).

## 2. Thanh toán VietQR

### Cách hoạt động

1. Thu ngân mở đơn ở POS → thanh toán → chọn hình thức **VietQR Transfer**.
2. Màn hình hiện mã QR VietQR của đơn: số tiền cần thu (to, rõ), nội dung
   chuyển khoản (mã đơn nếu có), tên tài khoản + ngân hàng + số tài khoản.
3. Khách quét mã bằng app ngân hàng và chuyển khoản.
4. Thu ngân kiểm tra tiền đã về tài khoản rồi bấm **I Have Received the
   Payment** (Tôi đã nhận được tiền) → xác nhận lần nữa → đơn chuyển sang
   đã thanh toán.

QR được tạo từ ảnh chuẩn của VietQR (`img.vietqr.io`), **không cần API key**.
Tiền về tài khoản ngân hàng của cửa hàng, hạch toán vào tài khoản ngân hàng
(giống hình thức Bank Payment).

### Cấu hình

Dashboard → Cài đặt → Cài đặt POS → tab **VietQR**:

| Mục | Ý nghĩa |
|---|---|
| VietQR Enabled | Bật/tắt hình thức VietQR ở POS (mặc định tắt) |
| VietQR Bank Code | Mã ngắn của ngân hàng nhận tiền, ví dụ `VCB`, `TCB`, `MB`, `ACB`, `BIDV` |
| VietQR Account Number | Số tài khoản nhận tiền (hiện trên mã QR) |
| VietQR Account Name | Tên chủ tài khoản, hiện trên mã QR |
| VietQR QR Template | Kiểu ảnh QR: chỉ mã QR, gọn, gọn có chi tiết (mặc định), in ấn |
| VietQR Transfer Content | Nội dung chuyển khoản, mặc định `{code}` = mã đơn, tối đa 25 ký tự |

Chưa nhập đủ ngân hàng + số tài khoản thì POS không hiện QR sai mà báo rõ
“VietQR is not configured” (chưa cấu hình VietQR). Cửa hàng chưa đổi tiền tệ
sang VND thì POS báo VietQR chỉ dùng với VND, vì mã QR VietQR luôn tính
bằng đồng.

### Nội dung chuyển khoản là gì

- Đơn đã có mã (đơn giữ lại, đơn sửa lại): nội dung là mã đơn, dạng
  `yymmdd-NNN` (ví dụ `261108-001`).
- Đơn mới chưa có mã (mã chỉ được cấp khi gửi đơn lên máy chủ): nội dung là
  mã tạm `NSyymmddHHmm` theo giờ tạo mã QR. Thu ngân đối soát bằng số tiền
  + giờ chuyển khoản, và mã đơn thật in trên hóa đơn sau khi gửi đơn.

### Lưu ý

- Ảnh QR tải từ `img.vietqr.io` nên máy POS cần có mạng. Mất mạng thì ảnh
  không tải được, màn hình báo lỗi và có nút thử lại.
- Số tiền trong QR = số còn phải thu (tổng đơn trừ đi tiền đã thu trước đó),
  làm tròn về đồng, không âm. Nếu đơn đã thu đủ thì không hiện QR.
- VietQR chỉ là hình thức thanh toán mới (`vietqr-payment`), quản lý được
  ở mục Payment Types với quyền `nexopos.manage-payments-types` như các
  hình thức khác; có thể tắt (deactivate) mà không xóa.

## 3. Giao diện POS đã cải thiện

- Popup thanh toán hiện thanh **Remaining to Collect** (còn phải thu) to rõ
  phía trên, áp dụng cho mọi hình thức thanh toán.
- Màn hình VietQR: số tiền cần thu to nhất, nội dung chuyển khoản rõ,
  trạng thái **Waiting for bank transfer** (đang chờ chuyển khoản) với chấm
  nhấp nháy, nút xác nhận đã nhận tiền to và tách riêng.
- Sửa nhãn “Credit Card” trong hình thức thẻ (trước đây ghi cứng tiếng Anh,
  nay dùng bản dịch).

## 4. Thử tay

1. Cài đặt chung → Tiền tệ → chọn preset VND → lưu → mở POS: giá hiện dạng
   `1.000.000 ₫`, không có số lẻ.
2. Cài đặt POS → tab VietQR → nhập ngân hàng + số tài khoản + tên tài khoản
   → bật VietQR → lưu.
3. Ở POS tạo đơn 50.000 ₫ → thanh toán → chọn VietQR Transfer: mã QR hiện
   đúng 50.000, nội dung là mã đơn (hoặc mã tạm `NS...` nếu đơn mới).
4. Quét QR bằng app ngân hàng thật, chuyển khoản đúng số tiền và nội dung.
5. Thu ngân kiểm tra tiền về rồi bấm xác nhận → đơn chuyển đã thanh toán,
   tiền hạch toán vào tài khoản ngân hàng.
6. Thử trường hợp chưa cấu hình: tắt VietQR hoặc xóa số tài khoản → mở
   VietQR ở POS phải báo chưa cấu hình, không hiện QR sai.

## 5. Việc tương lai: VNPay tự động xác nhận (chưa làm)

Hiện tại nhân viên vẫn xác nhận tay. Để VNPay tự động xác nhận cần thêm:

- Đăng ký VNPay, lấy `TMN Code` + `Hash Secret`, cấu hình IPN URL trỏ về
  NexoPOS.
- Endpoint nhận IPN, xác minh chữ ký `vnp_SecureHash`, đối soát
  `vnp_TxnRef` (mã đơn) + `vnp_Amount` + `vnp_ResponseCode == 00`.
- Khi IPN hợp lệ, tự đánh dấu đơn đã thanh toán và cập nhật màn hình POS
  (realtime/polling) thay vì chờ nhân viên bấm xác nhận.

VNPay không dùng chung mã QR VietQR ở trên: VNPay có luồng tạo link/QR
riêng qua API của VNPay.
