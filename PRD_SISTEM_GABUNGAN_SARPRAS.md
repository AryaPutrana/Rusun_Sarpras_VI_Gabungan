# PRD - Product Requirement Document
## Sistem Gabungan Sarana dan Prasarana UPRS VI

**Version**: 1.0.0  
**Last Updated**: 26 November 2026  
**Status**: Production Ready  
**Database**: Database_Gabungan_Sarpras

---

## 📋 EXECUTIVE SUMMARY

Sistem Gabungan Sarana dan Prasarana UPRS VI adalah aplikasi web terintegrasi yang menggabungkan 3 subsistem utama:
1. **Monitoring Harian** - Pemeriksaan air dan infrastruktur
2. **Laporan Bulanan** - Pelaporan keuangan bulanan
3. **Inventory Material** - Manajemen barang dan pengambilan material

**Tech Stack**:
- Backend: Laravel 10.50.3
- Frontend: Bootstrap 5, Chart.js 4.4.1
- Database: MySQL/MariaDB
- Server: Apache/Nginx (Laragon development)

---

## 🎯 PROJECT GOALS

### Primary Goals:
1. ✅ Menyatukan 3 sistem terpisah menjadi 1 aplikasi terintegrasi
2. ✅ Menyediakan dashboard real-time untuk monitoring
3. ✅ Memudahkan pencatatan dan pelaporan operasional
4. ✅ Tracking inventory material dan pengambilan barang
5. ✅ Role-based access control (Admin vs Petugas)

### Success Metrics:
- ✅ Zero data loss dari sistem lama ke database gabungan
- ✅ 100% feature parity dengan sistem sebelumnya
- ✅ Response time < 2 detik untuk semua halaman
- ✅ Zero critical security vulnerabilities
- ✅ Mobile-responsive untuk akses lapangan

---

## 👥 USER ROLES & PERMISSIONS

### 1. Admin
**Access Level**: Full Access

**Dapat:**
- ✅ Melihat semua data (monitoring, finance, inventory)
- ✅ Kelola lokasi monitoring & finance
- ✅ Kelola user (create, edit, delete)
- ✅ Kelola rusun (master data)
- ✅ Kelola items & withdrawals
- ✅ Export reports (PDF, Excel)
- ✅ View analytics & charts

**Tidak Dapat:**
- N/A (full access)

**Menu Khusus Admin:**
- Kelola Pengguna
- Lokasi Monitoring (CRUD)
- Lokasi Bulanan (CRUD)
- Laporan Monitoring (view all users)
- Laporan Bulanan (view all)

---

### 2. Petugas
**Access Level**: Limited Access

**Dapat:**
- ✅ Input monitoring harian (own data)
- ✅ View histori monitoring sendiri
- ✅ Input laporan bulanan
- ✅ View riwayat laporan bulanan sendiri
- ✅ View inventory items (read-only untuk data)
- ✅ Create withdrawal / pengambilan barang
- ✅ View laporan pengambilan
- ✅ Export own reports

**Tidak Dapat:**
- ❌ Kelola user lain
- ❌ Kelola lokasi (monitoring & finance)
- ❌ Edit/delete monitoring orang lain
- ❌ View all users data (hanya milik sendiri)
- ❌ Kelola master data (rusun, items master)

**Menu Khusus Petugas:**
- Input Monitoring (create form)
- Histori Monitoring (own data)
- Input Bulanan (create form)
- Riwayat Bulanan (own data)
- Data Barang (read-only)
- Pengambilan Barang (can create)

---

## 🏗️ SYSTEM ARCHITECTURE

### Database Schema

**Users & Auth**:
- `users` - User authentication & profiles
- `password_reset_tokens` - Password reset functionality

**Monitoring System**:
- `monitoring_locations` - Lokasi pemeriksaan (2 aktif)
- `water_monitorings` - Data pemeriksaan harian
  - Foreign keys: location_id, user_id
  - Columns: tanggal, waktu, sesi (pagi/siang/sore), kondisi, keterangan, foto

**Finance System**:
- `finance_locations` - Lokasi untuk laporan keuangan (1: Umum)
- `finance_reports` - Laporan keuangan bulanan
  - Foreign keys: location_id, user_id
  - Columns: tanggal, kondisi, foto, keterangan

**Inventory System**:
- `rusun` - Master data rusun (6: RABEK, UMEN, TIPAR, ALBO, CBT, KM2)
- `items` - Master barang (13 items)
  - Columns: item_code, name, photo, unit_price, stock, min_stock, unit, description
- `withdrawals` - Header pengambilan barang (7 withdrawals)
  - Foreign keys: rusun_id
  - Columns: taken_by, taken_at, description, total_quantity, total_value
- `withdrawal_items` - Detail items per withdrawal (11 detail)
  - Foreign keys: withdrawal_id, item_id
  - Columns: quantity, unit_price, subtotal
- `stock_adjustments` - Audit trail perubahan stok (3 adjustments)
  - Foreign keys: item_id, user_id
  - Columns: type (add/subtract), quantity, reason

**Total Tables**: 12 (production optimized)

---

## 🎨 USER INTERFACE

### Design System:
- **Framework**: Bootstrap 5.3.3
- **Icons**: Bootstrap Icons 1.11.3
- **Charts**: Chart.js 4.4.1
- **Color Scheme**: 
  - Primary: Bootstrap Blue (#0d6efd)
  - Success: Green (#198754)
  - Warning: Yellow (#ffc107)
  - Danger: Red (#dc3545)
  - Inventory Section: Purple gradient (#667eea to #764ba2)

### Key UI Components:

**1. Dashboard**
- Stats cards (Total Pemeriksaan, Lokasi Aktif, Inventory stats)
- Doughnut chart: Rekap Kondisi
- Stacked bar chart: Kondisi per Lokasi
- Real-time inventory overview
- Recent activities table

**2. Navbar**
- Responsive dropdown menu
- Smooth animations (fade + slide)
- Mobile-friendly (collapse from top, not sidebar)
- Role-based menu visibility

**3. Forms**
- CSRF protection on all forms
- Validation feedback (inline errors)
- File upload with preview
- Date/time pickers
- Searchable dropdowns

**4. Tables**
- Pagination (10-15 records per page)
- Search/filter functionality
- Sortable columns
- Action buttons (Edit, Delete, View)
- Responsive (stack on mobile)

---

## 🔐 SECURITY FEATURES

### Authentication & Authorization:
- ✅ Laravel authentication middleware
- ✅ Password hashing (bcrypt)
- ✅ Session management
- ✅ Remember me functionality
- ✅ CSRF protection on all POST/PUT/DELETE
- ✅ Role-based access control (admin/petugas)

### Data Security:
- ✅ SQL injection protection (Eloquent ORM)
- ✅ XSS protection (Blade auto-escaping)
- ✅ Foreign key constraints (data integrity)
- ✅ Soft deletes (audit trail preservation)
- ✅ Input validation on all forms
- ✅ File upload validation (type, size, extension)

### Best Practices:
- ✅ Environment variables for sensitive data (.env)
- ✅ Credentials file gitignored (KREDENSIAL_LOGIN.txt)
- ✅ No hardcoded passwords
- ✅ Secure session configuration
- ✅ Rate limiting on login attempts

---

## 📊 FEATURES BREAKDOWN

### 1. MONITORING HARIAN

**Purpose**: Track daily water/infrastructure monitoring

**Features**:
- ✅ Input pemeriksaan (tanggal, waktu, sesi, kondisi, foto, keterangan)
- ✅ 3 sesi per hari (Pagi, Siang, Sore)
- ✅ 3 kondisi (Normal, Debit Turun, Tekanan Air Kecil)
- ✅ Photo upload & storage
- ✅ Duplicate prevention (location + date + sesi unique)
- ✅ Filter by date range & location
- ✅ Dashboard charts (doughnut & stacked bar)
- ✅ Alert untuk kondisi tidak normal

**Admin Features**:
- Create/Edit/Delete lokasi monitoring
- View all users' monitoring data
- Access to full history

**Petugas Features**:
- Input monitoring untuk diri sendiri
- View own monitoring history only
- Cannot access other users' data

---

### 2. LAPORAN BULANAN

**Purpose**: Monthly financial/operational reporting

**Features**:
- ✅ Input laporan bulanan per lokasi
- ✅ Photo upload untuk bukti
- ✅ Kondisi tracking
- ✅ Filter by month
- ✅ Dashboard summary (current month)
- ✅ PDF export functionality
- ✅ History/archive view

**Admin Features**:
- Create/Edit/Delete lokasi bulanan
- View all reports
- Full history access

**Petugas Features**:
- Input laporan bulanan
- View own reports only
- Riwayat bulanan (own data)

---

### 3. INVENTORY MATERIAL

**Purpose**: Material inventory management & withdrawal tracking

**Features**:

**Items Management**:
- ✅ Master data barang (13 items)
- ✅ Item code (BRG-001, BRG-002, ...)
- ✅ Photo upload & thumbnail generation
- ✅ Stock tracking (current stock, min stock)
- ✅ Unit price & unit measurement
- ✅ Low stock alerts (stock <= min_stock)
- ✅ Stock adjustment with audit trail
- ✅ Search & filter

**Withdrawals/Pengambilan**:
- ✅ Multi-item withdrawal (1 transaction = multiple items)
- ✅ Header: taken_by, rusun, taken_at, description
- ✅ Detail: item, quantity, unit_price, subtotal
- ✅ Auto-calculate total_quantity & total_value
- ✅ Stock deduction on approval
- ✅ Edit functionality (preserve historical prices)
- ✅ Deadlock prevention (consistent lock ordering)

**Reporting**:
- ✅ Laporan pengambilan (filter by date range, rusun)
- ✅ PDF export dengan thumbnail
- ✅ Excel export
- ✅ Grouped by withdrawal (rowspan for header)
- ✅ Financial calculations (BCMath precision)

**Dashboard Stats**:
- ✅ Total Barang
- ✅ Total Pengambilan
- ✅ Bulan Ini
- ✅ Total Nilai (Rp)
- ✅ Pengambilan Terbaru (5 recent)
- ✅ Stok Menipis (5 low stock items)

---

## 🎯 FUNCTIONAL REQUIREMENTS

### FR-001: User Authentication
- **Priority**: Critical
- **Status**: ✅ Implemented
- User dapat login dengan email & password
- Password di-hash dengan bcrypt
- Session management dengan expiry
- Remember me functionality
- Logout clears session

### FR-002: Role-Based Access Control
- **Priority**: Critical
- **Status**: ✅ Implemented
- 2 roles: admin, petugas
- Middleware validation on routes
- UI menu adjustment based on role
- Data filtering (petugas sees own data only)

### FR-003: Dashboard
- **Priority**: High
- **Status**: ✅ Implemented
- Real-time stats cards
- Chart.js visualizations (doughnut, bar)
- Inventory overview section
- Recent activities
- Filter by date range

### FR-004: Monitoring System
- **Priority**: High
- **Status**: ✅ Implemented
- CRUD monitoring records
- Photo upload & storage
- 3 sesi per day validation
- Duplicate prevention
- Filter & search
- Charts & analytics

### FR-005: Finance System
- **Priority**: High
- **Status**: ✅ Implemented
- Monthly reporting
- Photo evidence upload
- Filter by month
- PDF export
- History view

### FR-006: Inventory System
- **Priority**: High
- **Status**: ✅ Implemented
- Items CRUD
- Multi-item withdrawals
- Stock tracking & adjustment
- Low stock alerts
- Audit trail
- PDF/Excel reports

### FR-007: Export Functionality
- **Priority**: Medium
- **Status**: ✅ Implemented
- PDF export (DOMPDF)
- Excel export
- Base64 image embedding for PDF
- Proper formatting & rowspan

### FR-008: Mobile Responsive
- **Priority**: High
- **Status**: ✅ Implemented
- Bootstrap responsive grid
- Touch-friendly UI
- Dropdown collapse dari atas (not sidebar)
- Readable on mobile devices

---

## 🔧 NON-FUNCTIONAL REQUIREMENTS

### NFR-001: Performance
- **Target**: Page load < 2 seconds
- **Status**: ✅ Met
- Database indexes on frequently queried columns
- Eager loading to prevent N+1 queries
- Pagination for large datasets
- Chart.js with defer loading

### NFR-002: Scalability
- **Target**: Support 100+ concurrent users
- **Status**: ✅ Ready
- Optimized queries
- Connection pooling
- Cache for thumbnails (30 days TTL)

### NFR-003: Reliability
- **Target**: 99% uptime
- **Status**: ✅ Ready
- Database transactions for data integrity
- Foreign key constraints
- Error handling & logging
- Backup strategy (manual mysqldump)

### NFR-004: Maintainability
- **Target**: Clean, documented code
- **Status**: ✅ Met
- Laravel best practices
- MVC architecture
- Commented complex logic
- Migration files for schema changes
- 21 migrations (all necessary)

### NFR-005: Security
- **Target**: Zero critical vulnerabilities
- **Status**: ✅ Met
- CSRF protection
- SQL injection prevention
- XSS protection
- Password hashing
- Input validation
- Role-based authorization

### NFR-006: Usability
- **Target**: Intuitive UI
- **Status**: ✅ Met
- Bootstrap consistent design
- Clear labeling
- Validation feedback
- Error messages in Indonesian
- Help text where needed

---

## 📁 PROJECT STRUCTURE

```
Gabungan_Sistem_Sarpras/
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Admin/
│   │   │   │   ├── FinanceLocationController.php
│   │   │   │   ├── MonitoringLocationController.php
│   │   │   │   └── UserController.php
│   │   │   ├── Inventory/
│   │   │   │   ├── ItemController.php
│   │   │   │   ├── ReportController.php
│   │   │   │   └── WithdrawalController.php
│   │   │   ├── AuthController.php
│   │   │   ├── DashboardController.php
│   │   │   ├── FinanceReportController.php
│   │   │   ├── MonitoringController.php
│   │   │   └── PhotoController.php
│   │   └── Middleware/
│   │       └── Authenticate.php
│   └── Models/
│       ├── FinanceLocation.php
│       ├── FinanceReport.php
│       ├── Item.php
│       ├── MonitoringLocation.php
│       ├── Rusun.php
│       ├── StockAdjustment.php
│       ├── User.php
│       ├── WaterMonitoring.php
│       ├── Withdrawal.php
│       └── WithdrawalItem.php
├── database/
│   ├── migrations/ (21 files)
│   └── seeders/
│       ├── DatabaseSeeder.php
│       ├── MonitoringLocationSeeder.php
│       ├── RusunSeeder.php
│       └── UserSeeder.php
├── resources/
│   └── views/
│       ├── auth/
│       │   └── login.blade.php
│       ├── inventory/
│       │   ├── items/
│       │   ├── reports/
│       │   └── withdrawals/
│       ├── layouts/
│       │   └── app.blade.php
│       ├── dashboard.blade.php
│       └── welcome.blade.php
├── routes/
│   └── web.php (58 routes)
├── tests/
│   └── Feature/
│       ├── DashboardTest.php (24 tests)
│       ├── Inventory/
│       └── ...
├── .env (Database_Gabungan_Sarpras)
├── KREDENSIAL_LOGIN.txt
├── END_TO_END_TESTING_REPORT.md
├── Gabungan sistem sarpras.docx
└── README.md
```

---

## 🗄️ DATA MIGRATION HISTORY

**Migration dari Database Lama**:

**Source**: `sistem_inventory_uprs_vi`  
**Target**: `Database_Gabungan_Sarpras`  
**Date**: 26 November 2026

**Migrated Data**:
- ✅ Rusun: 6 records (RABEK, UMEN, TIPAR, ALBO, CBT, KM2)
- ✅ Items: 12 records → now 13 (BRG-001 to BRG-013)
- ✅ Withdrawals: 6 records → now 7
- ✅ Withdrawal Items: 11 records
- ✅ Stock Adjustments: 3 records

**Data Integrity**:
- ✅ All foreign keys preserved
- ✅ Historical prices maintained
- ✅ Zero data loss
- ✅ Audit trail intact

---

## 🚀 DEPLOYMENT INFORMATION

### Production Requirements:
- **Server**: Apache 2.4+ or Nginx 1.18+
- **PHP**: 8.1+
- **Database**: MySQL 5.7+ or MariaDB 10.3+
- **Storage**: 1GB minimum (for photos)
- **Memory**: 512MB PHP memory_limit
- **Extensions**: PDO, OpenSSL, Mbstring, Tokenizer, XML, Ctype, JSON, BCMath, GD/Imagick

### Environment Configuration:
```env
APP_NAME="Sistem Gabungan Sarpras"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://your-domain.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=Database_Gabungan_Sarpras
DB_USERNAME=your_username
DB_PASSWORD=your_password
```

### Deployment Steps:
1. Clone repository
2. `composer install --optimize-autoloader --no-dev`
3. `cp .env.example .env` dan configure
4. `php artisan key:generate`
5. `php artisan migrate` (21 migrations)
6. `php artisan storage:link`
7. Set permissions (storage/ dan bootstrap/cache/)
8. Configure web server
9. `php artisan config:cache`
10. `php artisan route:cache`
11. `php artisan view:cache`

### Post-Deployment:
- Create admin user via `php artisan tinker`
- Seed master data (rusun, locations)
- Test login & basic functionality
- Setup daily backup cron job
- Monitor logs

---

## 🧪 TESTING COVERAGE

**Test Suite**: PHPUnit  
**Total Tests**: 343  
**Status**: ✅ All Passing

**Coverage by Module**:
- ✅ Authentication: 20 tests
- ✅ Dashboard: 24 tests
- ✅ Monitoring: 44 tests
- ✅ Finance: 30+ tests
- ✅ Inventory: 100+ tests
- ✅ Withdrawals: 50+ tests
- ✅ Stock Management: 20+ tests

**Test Types**:
- Unit tests: Model logic, calculations
- Feature tests: HTTP requests, routes, controllers
- Integration tests: Multi-model operations
- Browser tests: End-to-end user flows (11 scenarios)

**Latest E2E Test Results** (26 Nov 2026):
- ✅ Login Admin: PASS
- ✅ Dashboard Stats: PASS
- ✅ Dropdown Menus: PASS
- ✅ Inventory Navigation: PASS
- ✅ Logout & Login Petugas: PASS
- ✅ Role Authorization: PASS (admin menu hidden for petugas)
- ✅ Chart Rendering: PASS (Chart.js DOMContentLoaded fix)
- ✅ Session Management: PASS

---

## 🐛 KNOWN ISSUES & LIMITATIONS

### Issues:
**None** - All critical and high-priority bugs resolved

### Limitations:
1. **Photo Storage**: Local disk only (not cloud storage)
   - Mitigation: Can be extended to S3/Google Cloud Storage
   
2. **Concurrent Editing**: No real-time conflict detection
   - Mitigation: Last-write-wins, audit trail for tracking
   
3. **Export Size**: Large date range exports may timeout
   - Mitigation: 500-1000 record limit, pagination suggested

4. **Browser Support**: Optimized for modern browsers (Chrome, Firefox, Edge)
   - IE11 not supported (uses modern JS features)

5. **Mobile Features**: Limited compared to desktop
   - All features accessible but optimized for tablet/desktop workflow

---

## 📈 FUTURE ENHANCEMENTS

### Phase 2 Roadmap:

**Short-term (1-3 months)**:
- [ ] Email notifications untuk low stock alerts
- [ ] Advanced analytics (trends, predictions)
- [ ] Bulk operations (mass edit, mass delete)
- [ ] API endpoints untuk mobile app integration
- [ ] Dark mode theme

**Mid-term (3-6 months)**:
- [ ] Mobile app (Flutter/React Native)
- [ ] Real-time notifications (WebSocket)
- [ ] Advanced reporting (custom report builder)
- [ ] Integration dengan sistem akuntansi
- [ ] Barcode scanning untuk inventory

**Long-term (6-12 months)**:
- [ ] Multi-tenant support (multiple UPRS)
- [ ] Cloud deployment (AWS/GCP)
- [ ] Machine learning for demand forecasting
- [ ] Integration dengan IoT sensors (water monitoring)
- [ ] Blockchain untuk audit trail immutability

---

## 👥 STAKEHOLDERS

**Product Owner**: DPRKP DKI Jakarta  
**Development Team**: Aryap (Laravel Developer)  
**End Users**: 
- Admin Sarpras (2 users)
- Petugas Sarpras (2+ users)
- UPRS VI Staff

**Support Contact**: 
- Developer: [Contact info]
- System Admin: [Contact info]

---

## 📝 CHANGE LOG

### Version 1.0.0 (26 November 2026)
- ✅ Initial production release
- ✅ 3 subsystems integrated (Monitoring, Finance, Inventory)
- ✅ 21 migrations executed successfully
- ✅ Data migration completed (inventory from old DB)
- ✅ All 343 tests passing
- ✅ Security audit completed
- ✅ Performance optimization done
- ✅ Chart.js rendering fix implemented
- ✅ End-to-end testing completed (11/11 scenarios)
- ✅ Documentation completed

### Previous Development:
- 25 November 2026: Deployment readiness audit
- 25 November 2026: Comprehensive testing phase
- October 2026: Individual systems development
- September 2026: Initial project setup

---

## 📚 DOCUMENTATION REFERENCES

**Available Documents**:
1. `README.md` - Laravel framework documentation
2. `KREDENSIAL_LOGIN.txt` - System credentials (gitignored)
3. `END_TO_END_TESTING_REPORT.md` - Latest E2E test results
4. `Gabungan sistem sarpras.docx` - Original requirements
5. `PRD_SISTEM_GABUNGAN_SARPRAS.md` - This document

**External Resources**:
- Laravel 10.x Documentation: https://laravel.com/docs/10.x
- Bootstrap 5 Documentation: https://getbootstrap.com/docs/5.3
- Chart.js Documentation: https://www.chartjs.org/docs/latest/

---

## ✅ SIGN-OFF

**Project Status**: ✅ **PRODUCTION READY**

**Approved by**:
- Developer: Aryap
- Date: 26 November 2026
- Test Status: 343/343 PASSED
- Security Audit: PASSED
- Performance: PASSED

**Deployment Recommendation**: **APPROVED FOR PRODUCTION**

---

*Document Version: 1.0.0*  
*Last Updated: 26 November 2026*  
*Next Review: 26 December 2026*
