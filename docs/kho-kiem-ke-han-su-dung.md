# Kiểm kê kho định kỳ & Lô hàng / Hạn sử dụng

Tài liệu mô tả 2 tính năng kho mới trên nhánh `viet-hoa` (repo
`libacku48-hash/NexoPOSHH`). Cả 2 đều bám vào code kho có sẵn của NexoPOS,
không viết lại logic trừ kho.

## 1. Kiểm kê kho định kỳ

### Luồng làm việc

```
Nháp → Đang kiểm → Đã duyệt
  └→ Đã hủy (không đổi kho)
```

1. **Tạo phiếu kiểm kê:** Dashboard → Kho (Inventory) → Tạo phiếu kiểm kê.
   Tìm món, nhập số lượng thực tế đếm được. Hệ thống tự chốt số lượng trên
   hệ thống lúc tạo phiếu và tính chênh lệch (`thực tế − hệ thống`).
2. **Lưu nháp:** phiếu ở trạng thái Nháp, sửa thoải mái, chưa đổi kho.
3. **Bắt đầu kiểm:** phiếu chuyển sang Đang kiểm. Vẫn sửa được số lượng.
4. **Duyệt phiếu:** chỉ phiếu Đang kiểm mới duyệt được, và chỉ vai trò có
   quyền duyệt. Khi duyệt, hệ thống:
   - Làm mới lại số lượng hệ thống (vì trong lúc kiểm vẫn có thể bán hàng),
     tính lại chênh lệch;
   - Tạo một phiếu điều chỉnh kho thường (ACTION_SET cho từng món có chênh
     lệch) qua đúng flow `ProductService::executeAdjustmentDraft()` — tức là
     lịch sử kho, báo cáo và đối soát đều ghi nhận như một lần điều chỉnh
     kho bình thường;
   - Khóa phiếu (không sửa/xóa được nữa).
5. **Hủy phiếu:** chỉ phiếu Nháp hoặc Đang kiểm mới hủy được, kho không đổi.

### Quyền

| Quyền | Admin | Quản trị cửa hàng | Thu ngân | Người dùng |
|---|---|---|---|---|
| Tạo / xem / sửa phiếu (`nexopos.create/read/update.stocktakes`) | ✅ | ✅ | ❌ | ❌ |
| Duyệt phiếu (`nexopos.approve.stocktakes`) | ✅ | ✅ | ❌ | ❌ |
| Xóa phiếu (`nexopos.delete.stocktakes`) | ✅ | ✅ | ❌ | ❌ |

Phiếu đã duyệt không xóa được (kể cả admin) — muốn sửa số liệu phải tạo
phiếu kiểm kê mới.

### Lưu ý

- Món có chênh lệch bằng 0 sẽ không tạo dòng điều chỉnh (tránh lỗi ghi lịch
  sử với số lượng 0).
- Số lượng thực tế không được âm.
- Kiểm kê chỉ có ý nghĩa với món đang bật quản lý kho (`stock_management`).

## 2. Lô hàng & hạn sử dụng (FIFO)

### Lô hàng là gì

Mỗi dòng nhập hàng (phiếu nhập → từng món) chính là một lô hàng: có số
lượng nhập, số lượng còn lại (`available_quantity`), ngày sản xuất
(`manufactured_date`, mới thêm) và hạn sử dụng (`expiration_date`, đã có
sẵn từ trước). Không tạo bảng lô riêng — dùng lại bảng
`nexopos_procurements_products` để tránh 2 nguồn sự thật.

### Nhập hạn sử dụng

- Khi sửa một dòng nhập hàng (Kho → nhập hàng → mở dòng sản phẩm), nhập
  **Ngày sản xuất** và **Hạn sử dụng**. Cả 2 đều không bắt buộc: hàng không
  có hạn sử dụng vẫn nhập bình thường.
- Lô không có hạn sử dụng thì không bao giờ bị coi là hết hạn.

### Xuất kho theo FIFO

Khi bán hàng (tại POS, QR order, hoặc đơn thường), hệ thống trừ kho theo
lô nhập trước (FIFO):

- Chỉ trừ các lô **còn hạn** (hoặc không có hạn sử dụng), theo thứ tự nhập
  hàng từ cũ đến mới.
- Lô **đã hết hạn không được xuất** — hệ thống tự bỏ qua khi trừ FIFO.
- Mỗi lần trừ đều ghi lại vào bảng `nexopos_product_batch_movements` (lô
  nào, đơn nào, bao nhiêu) để khi hủy/hoàn đơn thì hoàn trả đúng lô đã trừ.
- Hàng tồn từ trước khi có tính năng lô (không có lô nào) vẫn bán bình
  thường: phần không có lô để trừ được ghi nhận là "thiếu lô" (shortfall),
  tổng tồn kho của món vẫn là nguồn sự thật.
- Món combo (grouped product) cũng trừ FIFO cho từng nguyên liệu con.

### Cảnh báo hết hạn

- **Danh sách lô hàng:** Kho → Lô hàng. Bảng hiển thị tất cả các lô, sắp
  xếp theo hạn sử dụng gần nhất trước. Cột trạng thái: **OK** / **Sắp hết
  hạn** / **Đã hết hạn** / **Đã hết**.
- **Thông báo hằng ngày (00:05):** nếu có lô sắp hết hạn trong vòng X ngày
  hoặc đã hết hạn mà còn hàng, quản trị viên và quản trị cửa hàng nhận
  được thông báo kèm link tới danh sách lô hàng.
- **Số ngày cảnh báo** cấu hình được: Cài đặt → Cài đặt chung → tab
  **Kho** → "Số ngày cảnh báo hết hạn", mặc định **7 ngày**.

### Xử lý lô đã hết hạn

Lô đã hết hạn còn hàng không tự biến mất. Quản trị viên vào danh sách lô
hàng, bấm **Hủy lô hết hạn** trên lô đó: hệ thống tạo một bút toán trừ kho
(ACTION_REMOVED) đúng bằng số lượng còn lại của lô — vừa trừ tổng tồn của
món, vừa đưa số lượng còn lại của lô về 0. Lô chưa hết hạn hoặc đã hết
hàng thì không hủy được kiểu này.

### Giới hạn đã biết

- Hàng tồn cũ (nhập trước khi có lô) không gắn với lô nào: bán hàng vẫn
  trừ tổng tồn bình thường, chỉ là không trừ được vào lô cụ thể.
- Hàng thừa sau khi kiểm kê duyệt (đếm được nhiều hơn hệ thống) được cộng
  vào tổng tồn nhưng **không gắn vào lô nào** cho tới lần nhập hàng sau.
- Kiểm kê duyệt phiếu sẽ tự đối chiếu lô: thiếu hàng thì trừ lô theo FIFO
  (kể cả lô hết hạn, vì hàng thực tế đã không còn); thừa hàng thì để
  ngoài lô như trên.
