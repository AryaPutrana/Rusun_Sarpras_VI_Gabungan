# INVENTORY MATERIAL MODULE - MANUAL TEST REPORT

**Tested by:** Automated Testing Script  
**Date:** 2026-10-09  
**Module:** Inventory Material (Items, Withdrawals, Reports)  
**Controllers:** ItemController, WithdrawalController, ReportController

---

## TEST EXECUTION LOG

### 1. ITEMS CRUD TESTS

#### Test 1.1: Create Item with Photo
**Endpoint:** POST /inventory/items  
**Expected:** Item created successfully with photo upload  
**Status:** 

#### Test 1.2: Create Item - Validation Tests
**Validations to test:**
- item_code: required, unique, max:50
- name: required, max:255
- photo: required, file, ValidImage, max:5120 (5MB)
- unit_price: required, numeric, min:0, max:1000000000
- stock: required, integer, min:0, max defined
- min_stock: nullable, integer, min:0, max defined
- unit: required, max:50
- description: nullable, string, max:60000

**Status:**

#### Test 1.3: View Item List
**Endpoint:** GET /inventory/items  
**Expected:** Paginated list of items (10 per page), ordered by item_code ASC  
**Status:**

#### Test 1.4: View Item Detail
**Endpoint:** GET /inventory/items/{id}  
**Expected:** Show item details with photo  
**Status:**

#### Test 1.5: Edit Item - Add Stock
**Endpoint:** PATCH /inventory/items/{id}  
**Field:** add_stock (max: 1000000)  
**Expected:** Stock incremented, audit trail created  
**Status:**

#### Test 1.6: Edit Item - Subtract Stock
**Endpoint:** PATCH /inventory/items/{id}  
**Fields:** subtract_stock (max: current stock), subtract_reason (required_if)  
**Expected:** Stock decremented, audit trail created  
**Status:**

#### Test 1.7: Edit Item - Update Photo
**Endpoint:** PATCH /inventory/items/{id}  
**Expected:** Old photo deleted (if not used by other items), new photo saved  
**Status:**

#### Test 1.8: Delete Item
**Endpoint:** DELETE /inventory/items/{id}  
**Expected:** Item deleted only if no withdrawal history  
**Status:**

#### Test 1.9: Delete Item with History
**Endpoint:** DELETE /inventory/items/{id}  
**Expected:** Error - cannot delete item with withdrawal history  
**Status:**

#### Test 1.10: Min Stock Validation
**Test:** Set min_stock and verify validation  
**Expected:** min_stock must be <= stock  
**Status:**

---

### 2. WITHDRAWALS CRUD TESTS

#### Test 2.1: Create Withdrawal - Single Item
**Endpoint:** POST /inventory/withdrawals  
**Fields:**
- items: array with one item
- taken_by: string
- rusun_id: exists in rusun table
- taken_at: date, before_or_equal:now
- description: nullable

**Expected:** Withdrawal created, stock deducted  
**Status:**

#### Test 2.2: Create Withdrawal - Multiple Items
**Endpoint:** POST /inventory/withdrawals  
**Expected:** Max 50 items per transaction (MAX_ITEMS_PER_TRANSACTION)  
**Status:**

#### Test 2.3: Withdrawal - Stock Validation
**Test:** Try to withdraw more than available stock  
**Expected:** Error - "Stok tidak mencukupi"  
**Status:**

#### Test 2.4: Withdrawal - Duplicate Item Validation
**Test:** Select same item twice in one transaction  
**Expected:** Error - "Barang yang sama tidak boleh dipilih lebih dari sekali"  
**Status:**

#### Test 2.5: Withdrawal - Quantity Validation
**Test:** quantity > MAX_QUANTITY (1000000)  
**Expected:** Error - "Jumlah pengambilan maksimal 1.000.000 unit"  
**Status:**

#### Test 2.6: View Withdrawal List
**Endpoint:** GET /inventory/withdrawals  
**Expected:** Paginated list with search, date filters  
**Status:**

#### Test 2.7: View Withdrawal Detail
**Endpoint:** GET /inventory/withdrawals/{id}  
**Expected:** Show withdrawal with all items  
**Status:**

#### Test 2.8: Edit Withdrawal
**Endpoint:** PATCH /inventory/withdrawals/{id}  
**Expected:** Stock adjusted based on quantity changes  
**Status:**

#### Test 2.9: Delete Withdrawal
**Endpoint:** DELETE /inventory/withdrawals/{id}  
**Expected:** Withdrawal deleted, stock returned  
**Status:**

#### Test 2.10: Rusun Selection
**Test:** Verify rusun dropdown works  
**Expected:** List of rusun ordered by name  
**Status:**

---

### 3. STOCK TRACKING TESTS

#### Test 3.1: Stock Deduction on Withdrawal
**Expected:** Item.stock decremented by withdrawal quantity  
**Status:**

#### Test 3.2: Stock Restoration on Edit
**Expected:** Stock adjusted correctly when quantity changed  
**Status:**

#### Test 3.3: Stock Restoration on Delete
**Expected:** Stock restored when withdrawal deleted  
**Status:**

#### Test 3.4: Race Condition Protection
**Expected:** lockForUpdate() prevents concurrent stock issues  
**Status:**

#### Test 3.5: Audit Trail
**Expected:** StockAdjustment records created for add/subtract  
**Status:**

---

### 4. REPORTS TESTS

#### Test 4.1: View Reports Page
**Endpoint:** GET /inventory/reports  
**Expected:** Filter form with start_date, end_date, rusun_id  
**Status:**

#### Test 4.2: Filter by Date Range
**Expected:** Show withdrawals in date range  
**Status:**

#### Test 4.3: Filter by Rusun
**Expected:** Show withdrawals for selected rusun  
**Status:**

#### Test 4.4: Export Excel
**Endpoint:** GET /inventory/reports/export/excel  
**Expected:** CSV file with UTF-8 BOM  
**Status:**

#### Test 4.5: Export PDF
**Endpoint:** GET /inventory/reports/export/pdf  
**Expected:** PDF file with DomPDF  
**Status:**

#### Test 4.6: Report Totals Accuracy
**Expected:** Total quantity and value calculated from DB, not truncated collection  
**Status:**

#### Test 4.7: Date Validation
**Test:** Invalid date format, end_date < start_date  
**Expected:** Filter warning, not redirect loop  
**Status:**

---

### 5. PHOTO UPLOAD TESTS

#### Test 5.1: Valid Image Upload
**Formats:** jpg, jpeg, png, gif  
**Expected:** Photo saved with UUID filename  
**Status:**

#### Test 5.2: Invalid File Type
**Test:** Upload .txt file as photo  
**Expected:** Error from ValidImage rule  
**Status:**

#### Test 5.3: File Size Validation
**Test:** Upload file > 5MB  
**Expected:** Error - "Ukuran foto maksimal 5MB"  
**Status:**

#### Test 5.4: Photo Storage Path
**Expected:** Stored in storage/app/public/items/  
**Status:**

#### Test 5.5: Old Photo Cleanup
**Expected:** Old photo deleted only if not used by other items  
**Status:**

---

### 6. JQUERY/JAVASCRIPT TESTS

#### Test 6.1: Item Dropdown in Withdrawal Form
**Expected:** Select2 or similar dropdown for item selection  
**Status:**

#### Test 6.2: Dynamic Item Rows
**Expected:** Add/remove item rows in withdrawal form  
**Status:**

#### Test 6.3: Quantity Validation (Client-side)
**Expected:** Real-time validation of quantity vs stock  
**Status:**

#### Test 6.4: Subtotal Calculation
**Expected:** Auto-calculate subtotal = quantity * unit_price  
**Status:**

---

## BUGS FOUND

### BUG REPORT FORMAT:
**Bug ID:** INV-XXX  
**Severity:** Critical | High | Medium | Low  
**Module:** Items | Withdrawals | Reports  
**Title:** Brief description  
**Steps to Reproduce:**  
1. Step 1
2. Step 2

**Expected Result:**  
**Actual Result:**  
**Error Message/Screenshot:**  
**Impact:**  
**Affected Code:**  
**Suggested Fix:**

---

## SUMMARY

**Total Tests Planned:** 50+  
**Tests Passed:**  
**Tests Failed:**  
**Bugs Found:**  
**Critical Issues:**  
**Recommendations:**
