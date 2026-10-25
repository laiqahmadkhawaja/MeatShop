# Meat Shop Management System (Halal Meat POS & Inventory)

A complete, enterprise-grade, weight-based POS, inventory, purchasing, live animal slaughter/processing, customer ledger (Khata), supplier ledger, expense, shift register, and profit-management system developed in **Core Procedural PHP, MySQL, Bootstrap 5, and JavaScript/AJAX**.

---

## 🌟 Key Capabilities & Modules

1. **Dashboard & Real-Time Performance Analytics (`index.php`)**
   - Essential KPIs: Today's Total Sales, Cash vs Credit Sales, Today's Purchases, Today's Operating Expenses.
   - Financial P&L: Real-time **Gross Profit** (Net Sales − COGS) and **Net Operating Profit**.
   - Current drawer cash, customer outstanding khata (receivables), and supplier payables.
   - Low-stock warnings and wastage/shrinkage metrics.
   - Interactive Chart.js charts: 7-day sales trend and sales distribution by meat category.

2. **POS & Live Weighing Scale Billing (`pos/`)**
   - Weight-based billing with support for KG, Grams, 500g, Piece, Whole Chicken, and Custom weights.
   - **Electronic Scale Integration (`assets/js/scale.js`)**: Connects to RS232 / USB scales via Web Serial API or interactive software scale simulator.
   - Real-time Tare weight deduction for butcher trays / containers.
   - Barcode scanning & search-as-you-type product lookup.
   - Customer Khata selector with instant "+ New Customer" modal.
   - Split payments (Cash, Bank, JazzCash, Easypaisa).
   - Multi-bill Hold & Recall functionality.
   - Direct 80mm/58mm thermal receipt printing and WhatsApp digital receipt sharing.

3. **Zero Hardcoding — Dynamic Real-Time Setup (`api/quick_add.php` & `settings/index.php`)**
   - Nothing is hardcoded! Everything is dynamically manageable in real-time without reloading:
     - Categories (Beef, Mutton, Chicken, Fish, Camel, etc.)
     - Meat Cut Types (Boneless, Bone-in, Mince, Ribs, Breast, Wings, etc.)
     - Measuring Units with conversion factors relative to 1 KG
     - Payment Methods & Bank / Mobile Wallet Accounts
     - Expense Categories (Rent, Electricity, Ice/Cooling, Salaries, Packaging, etc.)
     - Customers & Suppliers

4. **Live Animal Purchasing & Slaughtering / Processing (`animals/` & `processing/`)**
   - Record live animal purchases with ear tags, live weight, purchase rates, and transport charges.
   - Slaughter & Cutting breakdown: records prime saleable meat cuts, bone weight, organs/paya, trimmings, and cutting loss.
   - Automated **Meat Yield Percentage**:
     $$\text{Yield \%} = \frac{\text{Saleable Meat Weight}}{\text{Live Weight}} \times 100$$
   - Configurable cost allocation: Allocates live animal cost + slaughter costs across saleable meat cuts and credits inventory automatically.

5. **Meat Cutting Loss & Wastage Management (`wastage/`)**
   - Distinguishes between **normal operational shrinkage** (saw dust, bone trimming) and **spoilage / storage loss**.
   - Calculates financial impact of waste and updates stock upon authorized approval.

6. **Purchasing & Supplier Ledger (`purchases/` & `suppliers/`)**
   - Inward meat intake with Gross weight, Tare weight, Net weight, transport, and unloading charges.
   - Automatic Moving Average Cost (MAC) calculation for inventory valuation.
   - Complete supplier transaction Khata ledger with debit/credit history and printable statements.

7. **Customer Management & Khata System (`customers/`)**
   - Khata management for regular customers, restaurants, hotels, and caterers.
   - Real-time running balance, WhatsApp statement reminders, and payment collection vouchers.

8. **Cash Register & Shift Management (`shifts/`)**
   - Shift opening with drawer cash float.
   - Real-time cash tracking: Cash sales + customer collections − supplier payments − cash expenses.
   - Reconciles expected closing cash with actual counted cash and computes cash discrepancies.
   - Printable shift closing audit report.

9. **Accurate Profit & Loss Financial Statement (`reports/profit_loss.php`)**
   - Accurate inventory costing: Net Sales Revenue − COGS = Gross Profit − Operating Expenses = Net Profit.
   - Detailed date-range filters (Today, This Week, This Month, Custom).

10. **Multi-Branch Stores & Database Backup (`settings/branches.php` & `settings/backup.php`)**
    - Multi-branch store management with inter-branch stock dispatch & receipt transfers.
    - One-click SQL database dump download.

---

## 🚀 Getting Started

### Requirements
- **WAMP / XAMPP** (Apache 2.4+, PHP 8.0+ with PDO MySQL)
- **MySQL / MariaDB** (Port 3306 or 3307)

### Installation
1. Place the project folder in `C:\wamp64\www\MeatShop\`.
2. Open your web browser and navigate to:
   ```
   http://localhost/MeatShop/
   ```
3. Database migrations and initial seed data run automatically on first launch!

### Demo Login Credentials
- **Administrator**:
  - Username: `admin`
  - Password: `admin123`
- **Cashier**:
  - Username: `cashier`
  - Password: `cashier123`
