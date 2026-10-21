# Test Plan Sistematis: VeriFace HRIS Biometric Pro
## End-to-End Business Process Testing

**Versi:** 1.0  
**Tanggal:** 2026-10-16  
**Status:** Ready for Testing

---

## Daftar Isi
1. [Overview Testing](#overview-testing)
2. [Environment Setup untuk Testing](#environment-setup-untuk-testing)
3. [Test Scope & Scenarios](#test-scope--scenarios)
4. [Test Case Detil](#test-case-detil)
5. [Checklist Execution](#checklist-execution)
6. [Reporting & Documentation](#reporting--documentation)

---

## Overview Testing

### Tujuan Testing
Memverifikasi keseluruhan alur bisnis sistem dari enrollment karyawan hingga perhitungan payroll berfungsi dengan baik dan akurat.

### Cakupan Testing (In Scope)
- ✅ Employee Registration & Face Enrollment
- ✅ Daily Attendance System
- ✅ Face Recognition & Liveness Detection
- ✅ Attendance Verification & Approval
- ✅ Payroll Calculation
- ✅ Report Generation
- ✅ Geofencing & Location Verification
- ✅ Data Integrity & Security

### Tidak Dicakup (Out of Scope)
- Performance/Load testing
- Security penetration testing
- Third-party API integration testing

### Timeline Testing
| Phase | Duration | Periode |
|-------|----------|---------|
| Preparation | 1 hari | Hari 1 |
| Smoke Testing | 0.5 hari | Hari 1 siang |
| Functional Testing | 3 hari | Hari 2-4 |
| Integration Testing | 2 hari | Hari 5-6 |
| UAT & Sign-off | 2 hari | Hari 7-8 |

---

## Environment Setup untuk Testing

### Pre-Test Requirements

#### 1. Test Data Preparation
```sql
-- Siapkan test employees di database
INSERT INTO employees (employee_id, name, email, department) VALUES
('EMP001', 'Test User 1', 'test1@company.com', 'IT'),
('EMP002', 'Test User 2', 'test2@company.com', 'HR'),
('EMP003', 'Test User 3', 'test3@company.com', 'Finance'),
('EMP004', 'Test User 4', 'test4@company.com', 'Operations'),
('EMP005', 'Test User 5', 'test5@company.com', 'Admin');
```

#### 2. Webcam Test Devices
- Webcam resolusi minimum 1280x720
- Lighting condition: Well-lit environment (300+ lux)
- 2 webcam untuk cross-testing (jika ada)

#### 3. System Clock Verification
```bash
# Pastikan waktu sistem akurat
w32tm /resync
```

#### 4. Database Fresh Start
```bash
# Backup database existing
mysqldump -u root hris_biometric > backup_hris_before_test.sql

# Reset test database
mysql -u root hris_biometric < test_data.sql
```

---

## Test Scope & Scenarios

### Phase 1: Employee Registration & Face Enrollment

#### Scenario 1.1: Successful Employee Registration
```
Flow: Admin → Employee Management → Add New Employee
```

#### Scenario 1.2: Face Capture & Enrollment
```
Flow: Registration → Face Capture → Save to DB
```

#### Scenario 1.3: Multiple Face Angles
```
Flow: Capture dari 5 angle berbeda → Liveness Check → Store
```

---

### Phase 2: Daily Attendance System

#### Scenario 2.1: Morning Check-In
```
Flow: Employee → Open App → Camera Capture → Face Recognition → Check-In Success
```

#### Scenario 2.2: Afternoon Check-Out
```
Flow: Employee → Open App → Camera Capture → Face Recognition → Check-Out Success
```

#### Scenario 2.3: Location Verification
```
Flow: Face Recognition + Geofencing Check → Attendance Recorded
```

---

### Phase 3: Attendance Verification & Approval

#### Scenario 3.1: Attendance Review
```
Flow: HR Admin → View Daily Attendance → Approve Valid Records
```

#### Scenario 3.2: Late Arrival Handling
```
Flow: Late Check-In → Auto-flagged → HR Review → Mark as Late
```

#### Scenario 3.3: Absence Handling
```
Flow: No Check-In & Check-Out → Mark as Absent → Notification
```

---

### Phase 4: Payroll Calculation

#### Scenario 4.1: Monthly Salary Calculation
```
Flow: Attendance Data → Basic Salary → Deductions → Net Salary
```

#### Scenario 4.2: Overtime Calculation
```
Flow: Extra Hours → Overtime Rate → Add to Salary
```

#### Scenario 4.3: Deduction Handling
```
Flow: Tax → Insurance → Loans → Applied to Payroll
```

---

### Phase 5: Report Generation

#### Scenario 5.1: Attendance Report
```
Flow: Select Period → Generate → Download Excel/PDF
```

#### Scenario 5.2: Payroll Report
```
Flow: Select Month → Generate → Verify Calculations → Download
```

#### Scenario 5.3: Analytics Dashboard
```
Flow: View Dashboard → Charts & Statistics → Export Data
```

---

## Test Case Detil

### TEST CASE 1: EMPLOYEE REGISTRATION

**Test ID:** TC-001-REG-001  
**Title:** Successful New Employee Registration  
**Priority:** HIGH  
**Type:** Functional

#### Pre-conditions
- Admin logged in
- Database is clean
- Camera is available

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Navigate to Employee Management → New Employee | Form loads successfully | ☐ Pass ☐ Fail |
| 2 | Fill Form: ID=EMP001, Name="Andi Wijaya", Dept="IT" | Form fields populated | ☐ Pass ☐ Fail |
| 3 | Click "Submit" button | Employee record created in DB | ☐ Pass ☐ Fail |
| 4 | System shows "Registration Successful" message | Confirmation displayed | ☐ Pass ☐ Fail |
| 5 | Verify in database: `SELECT * FROM employees WHERE employee_id='EMP001'` | Record exists with correct data | ☐ Pass ☐ Fail |

#### Post-conditions
- Employee EMP001 appears in system
- Ready for face enrollment

#### Actual Result (During Testing)
```
[Test Executor fills this during execution]
```

#### Notes
```
[Additional observations]
```

---

### TEST CASE 2: FACE ENROLLMENT

**Test ID:** TC-002-ENROLL-001  
**Title:** Successful Face Enrollment with Multiple Angles  
**Priority:** CRITICAL  
**Type:** Functional

#### Pre-conditions
- Employee EMP001 registered
- Webcam working properly
- Good lighting (300+ lux)

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Navigate to "Face Enrollment" | Enrollment page loads | ☐ Pass ☐ Fail |
| 2 | Select Employee EMP001 | Dropdown shows selected employee | ☐ Pass ☐ Fail |
| 3 | Click "Start Camera" | Webcam activates, live feed visible | ☐ Pass ☐ Fail |
| 4 | Position face in frame (straight) | System detects face, shows "Face Detected" | ☐ Pass ☐ Fail |
| 5 | Click "Capture Photo 1" | Photo saved, shows preview | ☐ Pass ☐ Fail |
| 6 | Turn head left (~45°) & capture | Photo 2 saved | ☐ Pass ☐ Fail |
| 7 | Turn head right (~45°) & capture | Photo 3 saved | ☐ Pass ☐ Fail |
| 8 | Look up & capture | Photo 4 saved | ☐ Pass ☐ Fail |
| 9 | Look down & capture | Photo 5 saved | ☐ Pass ☐ Fail |
| 10 | Click "Verify Liveness" | Liveness detection runs | ☐ Pass ☐ Fail |
| 11 | System confirms "Liveness OK" | Anti-spoofing passed | ☐ Pass ☐ Fail |
| 12 | Click "Save Enrollment" | Face DB updated, faces stored in `face_db/EMP001/` | ☐ Pass ☐ Fail |
| 13 | Verify in folder: `C:\xampp\htdocs\....\face_db\EMP001\` | 5 images exist | ☐ Pass ☐ Fail |

#### Post-conditions
- EMP001 face data enrolled
- Ready for attendance system

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 3: SUCCESSFUL MORNING CHECK-IN

**Test ID:** TC-003-ATT-001  
**Title:** Employee Morning Check-In with Face Recognition  
**Priority:** CRITICAL  
**Type:** Functional

#### Pre-conditions
- Employee EMP001 face enrolled
- Time is morning (before 12:00 PM)
- Webcam active
- Location within geofence

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Employee opens browser → index.html | Dashboard loads | ☐ Pass ☐ Fail |
| 2 | Click "Start Attendance" button | Camera permission prompt appears | ☐ Pass ☐ Fail |
| 3 | Grant camera permission | Webcam activates, live video feed visible | ☐ Pass ☐ Fail |
| 4 | Position face in frame | System detects face | ☐ Pass ☐ Fail |
| 5 | Click "Capture Face" | Snapshot taken, sent to Flask API | ☐ Pass ☐ Fail |
| 6 | System calls `/api/verify-face` endpoint | DeepFace recognition runs | ☐ Pass ☐ Fail |
| 7 | Face matches EMP001 in database | Match confidence > 0.6 | ☐ Pass ☐ Fail |
| 8 | System checks geofence | Location within 100m of office | ☐ Pass ☐ Fail |
| 9 | System checks liveness | Not a spoofing attempt | ☐ Pass ☐ Fail |
| 10 | Check-in recorded in database | INSERT into attendance table | ☐ Pass ☐ Fail |
| 11 | Browser shows success message | "Check-In Berhasil - 08:30:45" | ☐ Pass ☐ Fail |
| 12 | Verify in database: `SELECT * FROM attendance WHERE employee_id='EMP001' AND date=TODAY() AND check_in IS NOT NULL` | Record exists with timestamp | ☐ Pass ☐ Fail |

#### Post-conditions
- EMP001 attendance recorded for today
- Check-in timestamp accurate

#### Actual Result
```
[Test Executor fills this]
```

#### Notes
- Record exact time of check-in
- Verify timestamp in database matches browser display

---

### TEST CASE 4: AFTERNOON CHECK-OUT

**Test ID:** TC-003-ATT-002  
**Title:** Employee Afternoon Check-Out  
**Priority:** CRITICAL  
**Type:** Functional

#### Pre-conditions
- EMP001 already checked-in today
- Time is afternoon (after 12:00 PM)
- Same employee, same webcam

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Employee opens browser → index.html | Dashboard loads | ☐ Pass ☐ Fail |
| 2 | Scroll to "Check-Out" section | Check-out form visible | ☐ Pass ☐ Fail |
| 3 | Click "Start Check-Out" button | Camera activates | ☐ Pass ☐ Fail |
| 4 | Position face & click "Capture Face" | Face recognized as EMP001 | ☐ Pass ☐ Fail |
| 5 | System calls `/api/verify-face` | Verification successful | ☐ Pass ☐ Fail |
| 6 | Check-out time recorded | UPDATE attendance table with check_out time | ☐ Pass ☐ Fail |
| 7 | Browser shows success message | "Check-Out Berhasil - 17:30:22" | ☐ Pass ☐ Fail |
| 8 | Verify database | `SELECT * FROM attendance WHERE employee_id='EMP001' AND date=TODAY()` returns both check_in and check_out | ☐ Pass ☐ Fail |
| 9 | System calculates work hours | Display: "8 jam 60 menit" or similar | ☐ Pass ☐ Fail |

#### Post-conditions
- Complete attendance record for the day
- Work hours calculated

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 5: FACE RECOGNITION FAILURE - UNKNOWN PERSON

**Test ID:** TC-003-ATT-003  
**Title:** System Rejects Unknown Face (Negative Test)  
**Priority:** HIGH  
**Type:** Negative/Security

#### Pre-conditions
- Webcam active
- Test with someone NOT in face_db

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Unknown person positions face in camera | System detects face | ☐ Pass ☐ Fail |
| 2 | Click "Capture Face" | Image sent to Flask API | ☐ Pass ☐ Fail |
| 3 | System compares with face_db | No match found (confidence < 0.6) | ☐ Pass ☐ Fail |
| 4 | Browser shows error message | "Face Tidak Dikenali" or "Unauthorized" | ☐ Pass ☐ Fail |
| 5 | Image saved to unknown_db for audit | File saved in `unknown_db/` folder | ☐ Pass ☐ Fail |
| 6 | No attendance record created | Database INSERT does NOT happen | ☐ Pass ☐ Fail |
| 7 | System logs incident | Log entry in system (optional security logging) | ☐ Pass ☐ Fail |

#### Post-conditions
- Unknown attempt recorded for audit
- Attendance NOT created

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 6: SPOOFING DETECTION - PHOTO ATTACK

**Test ID:** TC-003-ATT-004  
**Title:** System Detects Liveness Spoofing (Print/Screen)  
**Priority:** CRITICAL  
**Type:** Security

#### Pre-conditions
- Printed photo of employee face
- OR: Second device with employee photo on screen

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Hold printed photo in front of camera | Camera captures photo (not live face) | ☐ Pass ☐ Fail |
| 2 | Click "Capture Face" | Image sent to liveness detection module | ☐ Pass ☐ Fail |
| 3 | Liveness detection analyzes image | Detects it's a static image, not real face | ☐ Pass ☐ Fail |
| 4 | System rejects capture | "Liveness Check Gagal - Face must be real" | ☐ Pass ☐ Fail |
| 5 | No attendance record created | Security prevented unauthorized access | ☐ Pass ☐ Fail |
| 6 | Spoofing attempt logged | Image saved to `spoof_db/` | ☐ Pass ☐ Fail |
| 7 | Admin notified (optional) | Alert generated for suspicious activity | ☐ Pass ☐ Fail |

#### Post-conditions
- Spoofing attempt blocked
- Evidence captured for audit

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 7: GEOFENCE VIOLATION

**Test ID:** TC-003-ATT-005  
**Title:** Attendance Rejected Outside Geofence  
**Priority:** HIGH  
**Type:** Location-Based Security

#### Pre-conditions
- Employee EMP001 face enrolled
- Testing outside office geofence (>100m from office)
- GPS/Location enabled
- Browser location permission granted

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Employee EMP001 at location >100m from office | Position outside geofence boundary | ☐ Pass ☐ Fail |
| 2 | Click "Start Attendance" | Camera activates | ☐ Pass ☐ Fail |
| 3 | Capture face | Face recognized correctly as EMP001 | ☐ Pass ☐ Fail |
| 4 | System checks location | Geofence check runs | ☐ Pass ☐ Fail |
| 5 | Location outside allowed radius | System detects violation | ☐ Pass ☐ Fail |
| 6 | Browser shows error | "Anda berada di luar area kantor. Check-in ditolak." | ☐ Pass ☐ Fail |
| 7 | No attendance record created | Database NOT updated | ☐ Pass ☐ Fail |
| 8 | Violation logged | Record saved for HR review | ☐ Pass ☐ Fail |

#### Post-conditions
- Geofence security working
- Remote clock-in prevented

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 8: LATE ARRIVAL HANDLING

**Test ID:** TC-004-LATE-001  
**Title:** System Flags Late Check-In After 09:00 AM  
**Priority:** MEDIUM  
**Type:** Business Logic

#### Pre-conditions
- Employee EMP001 not yet checked-in
- Current time is after 09:00 AM (late threshold)

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | At 09:15 AM, employee EMP001 performs check-in | Check-in recorded | ☐ Pass ☐ Fail |
| 2 | System compares check-in time with threshold (09:00) | Time is > threshold | ☐ Pass ☐ Fail |
| 3 | System marks as "LATE" in database | `status='LATE'` | ☐ Pass ☐ Fail |
| 4 | Browser shows warning message | "Anda Terlambat (15 menit)" | ☐ Pass ☐ Fail |
| 5 | HR Dashboard shows late indicator | Red flag or indicator visible | ☐ Pass ☐ Fail |
| 6 | Verify database | `SELECT * FROM attendance WHERE employee_id='EMP001' AND status='LATE'` | ☐ Pass ☐ Fail |

#### Post-conditions
- Late record flagged for payroll deduction
- HR can review in dashboard

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 9: ABSENCE - NO CHECK-IN

**Test ID:** TC-004-ABS-001  
**Title:** System Records Absence (No Check-In by End of Day)  
**Priority:** MEDIUM  
**Type:** Business Logic

#### Pre-conditions
- Employee EMP002 not checked-in
- Current time is end of business day (17:00 or later)

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Run daily job (scheduler) at 17:30 | System checks for missing check-ins | ☐ Pass ☐ Fail |
| 2 | EMP002 has no check-in record for today | Record is missing | ☐ Pass ☐ Fail |
| 3 | System creates ABSENT record | INSERT into attendance with status='ABSENT' | ☐ Pass ☐ Fail |
| 4 | HR Dashboard shows absence | EMP002 listed under "Absent Today" | ☐ Pass ☐ Fail |
| 5 | Notification sent to HR | Email/SMS notifying absent employee | ☐ Pass ☐ Fail |
| 6 | Verify database | Record shows ABSENT status with reason | ☐ Pass ☐ Fail |

#### Post-conditions
- Absence recorded for payroll impact
- HR notified for follow-up

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 10: DAILY ATTENDANCE REPORT GENERATION

**Test ID:** TC-005-REPORT-001  
**Title:** Generate Daily Attendance Report  
**Priority:** HIGH  
**Type:** Functional/Reporting

#### Pre-conditions
- At least 5 employees with attendance records
- Mixed statuses: present, late, absent
- Current date has attendance data

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | HR Admin clicks "Reports" menu | Report page loads | ☐ Pass ☐ Fail |
| 2 | Select "Daily Attendance Report" | Report type selected | ☐ Pass ☐ Fail |
| 3 | Select date (today) | Date picker shows correct date | ☐ Pass ☐ Fail |
| 4 | Click "Generate Report" | Report generated, table displays | ☐ Pass ☐ Fail |
| 5 | Report shows all employees | All 5 test employees listed | ☐ Pass ☐ Fail |
| 6 | Verify columns present | Employee ID, Name, Check-In, Check-Out, Status | ☐ Pass ☐ Fail |
| 7 | Verify data accuracy | Times match database records | ☐ Pass ☐ Fail |
| 8 | Click "Download Excel" | File downloaded as `.xlsx` | ☐ Pass ☐ Fail |
| 9 | Open Excel file | Data formatted correctly, readable | ☐ Pass ☐ Fail |
| 10 | Verify Excel calculations | Work hours calculated (check_out - check_in) | ☐ Pass ☐ Fail |

#### Post-conditions
- Report exported for HR records
- Data accurate and auditable

#### Actual Result
```
[Test Executor fills this]
```

---

### TEST CASE 11: PAYROLL CALCULATION - BASIC SALARY

**Test ID:** TC-006-PAYROLL-001  
**Title:** Calculate Monthly Payroll (Basic Salary + Attendance)  
**Priority:** CRITICAL  
**Type:** Functional/Financial

#### Pre-conditions
- 5 employees with complete month attendance
- Employee salary data in database
- Month: October 2026 (complete)

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | HR Admin clicks "Payroll" → "Calculate Monthly" | Payroll calculation page loads | ☐ Pass ☐ Fail |
| 2 | Select Month: October 2026 | Month selected | ☐ Pass ☐ Fail |
| 3 | Click "Calculate Payroll" | System processes attendance data | ☐ Pass ☐ Fail |
| 4 | System calculates work days | 22 working days (Oct 2026) | ☐ Pass ☐ Fail |
| 5 | For EMP001 with 20 present days: 10,000,000 salary | Calc: (10,000,000 / 22) × 20 = 9,090,909 | ☐ Pass ☐ Fail |
| 6 | For EMP002 with 1 absent day: 8,000,000 salary | Calc: (8,000,000 / 22) × 21 = 7,636,364 | ☐ Pass ☐ Fail |
| 7 | For EMP003 with 2 late days: 9,000,000 salary | Calc: (9,000,000 / 22) × 20 - late deduction | ☐ Pass ☐ Fail |
| 8 | System displays calculated payroll | Table shows all employees with calculations | ☐ Pass ☐ Fail |
| 9 | Verify totals match manual calculation | Cross-check with Excel formula | ☐ Pass ☐ Fail |
| 10 | HR reviews and clicks "Approve Payroll" | Payroll finalized in database | ☐ Pass ☐ Fail |
| 11 | System records approval timestamp | approval_date set to current date/time | ☐ Pass ☐ Fail |

#### Post-conditions
- Payroll calculated and approved
- Ready for payment processing

#### Actual Result
```
[Test Executor fills this]

Calculation Verification:
- EMP001: 10,000,000 / 22 × 20 = __________ ✓
- EMP002: 8,000,000 / 22 × 21 = __________ ✓
- EMP003: 9,000,000 / 22 × 20 - 50,000 = __________ ✓
```

---

### TEST CASE 12: PAYROLL WITH DEDUCTIONS

**Test ID:** TC-006-PAYROLL-002  
**Title:** Payroll Calculation with Tax & Insurance Deductions  
**Priority:** HIGH  
**Type:** Functional/Financial

#### Pre-conditions
- Employee with salary above tax threshold
- Tax rate configured: 10%
- Insurance premium: 100,000/month

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Navigate to Payroll → Deductions Settings | Deduction configuration page | ☐ Pass ☐ Fail |
| 2 | Verify Tax Rate: 10% | Tax rate correct | ☐ Pass ☐ Fail |
| 3 | Verify Insurance: 100,000 | Insurance amount correct | ☐ Pass ☐ Fail |
| 4 | Calculate payroll with deductions | System applies deductions | ☐ Pass ☐ Fail |
| 5 | For salary 10,000,000 (all days present) | Gross: 10,000,000 | ☐ Pass ☐ Fail |
| 6 | Tax calculated: 10,000,000 × 10% = 1,000,000 | Tax amount correct | ☐ Pass ☐ Fail |
| 7 | Insurance: 100,000 | Insurance deducted | ☐ Pass ☐ Fail |
| 8 | Net Salary = 10,000,000 - 1,000,000 - 100,000 = 8,900,000 | Final net salary shown | ☐ Pass ☐ Fail |
| 9 | Verify calculation in browser | Display shows: "Gross: Rp 10.000.000 | Tax: Rp 1.000.000 | Insurance: Rp 100.000 | Net: Rp 8.900.000" | ☐ Pass ☐ Fail |
| 10 | Generate payroll report with breakdown | PDF/Excel shows line items | ☐ Pass ☐ Fail |

#### Post-conditions
- Payroll with deductions calculated correctly
- Report shows breakdown for employee understanding

#### Actual Result
```
[Test Executor fills this]

Deduction Breakdown (verify):
- Gross Salary: _________________
- Tax (10%): _________________
- Insurance: _________________
- Other Deductions: _________________
- Net Salary: _________________
```

---

### TEST CASE 13: OVERTIME CALCULATION

**Test ID:** TC-006-PAYROLL-003  
**Title:** Overtime Hours & Compensation Calculation  
**Priority:** MEDIUM  
**Type:** Functional/Financial

#### Pre-conditions
- Employee with overtime records
- Overtime rate: 1.5x normal rate
- Standard work day: 8 hours

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | Navigate to Payroll → Overtime | Overtime management page | ☐ Pass ☐ Fail |
| 2 | View overtime entries for October | List shows dates & hours | ☐ Pass ☐ Fail |
| 3 | Employee EMP001: 5 days overtime × 2 hours = 10 hours | Overtime hours totaled | ☐ Pass ☐ Fail |
| 4 | Hourly rate: 10,000,000 / 22 / 8 = 56,818/hour | Hourly rate calculated | ☐ Pass ☐ Fail |
| 5 | Overtime payment: 56,818 × 1.5 × 10 = 852,270 | Overtime compensation calculated | ☐ Pass ☐ Fail |
| 6 | System adds to gross payroll | Gross increased by 852,270 | ☐ Pass ☐ Fail |
| 7 | Final net salary includes overtime | All deductions applied after | ☐ Pass ☐ Fail |
| 8 | Verify in payroll summary | "Overtime: Rp 852.270" visible | ☐ Pass ☐ Fail |

#### Post-conditions
- Overtime properly compensated
- Included in final payroll

#### Actual Result
```
[Test Executor fills this]

Overtime Calculation (verify):
- Hourly Rate: _________________ / hour
- Overtime Multiplier: 1.5x
- Total OT Hours: _________________ hours
- OT Compensation: _________________ 
```

---

### TEST CASE 14: HR APPROVAL WORKFLOW

**Test ID:** TC-007-APPROVAL-001  
**Title:** HR Reviews & Approves Attendance Records  
**Priority:** HIGH  
**Type:** Workflow/Functional

#### Pre-conditions
- Multiple attendance records in "PENDING APPROVAL" status
- HR user logged in
- Mix of normal, late, and problematic records

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | HR Admin clicks "Pending Approvals" | List of pending records displayed | ☐ Pass ☐ Fail |
| 2 | System shows pending count | "5 Records Pending" displayed | ☐ Pass ☐ Fail |
| 3 | HR reviews each record | Details: Employee, Time, Location, Face match confidence | ☐ Pass ☐ Fail |
| 4 | For normal check-ins: Click "Approve" | Record status = "APPROVED" | ☐ Pass ☐ Fail |
| 5 | For suspicious entries: Click "Review Details" | Shows face recognition confidence, location | ☐ Pass ☐ Fail |
| 6 | HR can reject or approve after review | Option available for both actions | ☐ Pass ☐ Fail |
| 7 | After approval, record locked (cannot modify) | Edit button disabled for approved records | ☐ Pass ☐ Fail |
| 8 | All pending records processed | Count shows "0 Records Pending" | ☐ Pass ☐ Fail |
| 9 | Approval history logged | timestamp & HR username recorded | ☐ Pass ☐ Fail |

#### Post-conditions
- All attendance finalized
- Ready for payroll processing

#### Actual Result
```
[Test Executor fills this]

Approval Summary:
- Total Records: _________
- Approved: _________
- Rejected: _________
- Needs Review: _________
```

---

### TEST CASE 15: COMPLETE END-TO-END SCENARIO (1 Day Cycle)

**Test ID:** TC-999-E2E-001  
**Title:** Complete Business Day Cycle  
**Priority:** CRITICAL  
**Type:** Integration/End-to-End

#### Pre-conditions
- 3 test employees: EMP001, EMP002, EMP003
- All faces enrolled
- 1 full business day (8:00 AM - 5:30 PM)

#### Test Steps

| # | Step | Expected Result | Status |
|---|------|-----------------|--------|
| 1 | **08:30 AM** - EMP001 checks in at office | Check-in recorded on-time | ☐ Pass ☐ Fail |
| 2 | **08:45 AM** - EMP002 checks in at office | Check-in recorded on-time | ☐ Pass ☐ Fail |
| 3 | **09:30 AM** - EMP003 checks in (LATE) | Check-in recorded with LATE flag | ☐ Pass ☐ Fail |
| 4 | **12:30 PM** - Lunch break time | System allows break without check-out | ☐ Pass ☐ Fail |
| 5 | **17:00 PM** - EMP001 checks out | Check-out recorded | ☐ Pass ☐ Fail |
| 6 | **17:15 PM** - EMP002 checks out | Check-out recorded | ☐ Pass ☐ Fail |
| 7 | **17:30 PM** (end of day) - Run end-of-day job | System auto-checks out EMP003 | ☐ Pass ☐ Fail |
| 8 | **Next Day 09:00 AM** - HR reviews dashboard | 3 attendance records visible | ☐ Pass ☐ Fail |
| 9 | HR approves all records | Status = APPROVED | ☐ Pass ☐ Fail |
| 10 | HR generates daily report | Report exported successfully | ☐ Pass ☐ Fail |
| 11 | Month-end: Calculate payroll | All 3 employees calculated correctly | ☐ Pass ☐ Fail |
| 12 | Payroll generated: EMP001 (20 days), EMP002 (21 days), EMP003 (19 days) | Payroll shows correct day counts | ☐ Pass ☐ Fail |
| 13 | Apply deductions (tax 10%, insurance 100k) | Net salaries calculated | ☐ Pass ☐ Fail |
| 14 | Generate payroll report | Report shows all details correctly | ☐ Pass ☐ Fail |
| 15 | Export to Excel for finance | File downloaded, data verified | ☐ Pass ☐ Fail |

#### Post-conditions
- Complete business day processed successfully
- Data from face capture → attendance → payroll verified end-to-end

#### Actual Result
```
[Test Executor fills this - Complete cycle summary]

TIMELINE SUMMARY:
08:30 - EMP001 Check-in: __________ ✓
08:45 - EMP002 Check-in: __________ ✓
09:30 - EMP003 Check-in (LATE): __________ ✓
17:00 - EMP001 Check-out: __________ ✓
17:15 - EMP002 Check-out: __________ ✓
17:30 - EMP003 Auto Check-out: __________ ✓

PAYROLL CALCULATION:
EMP001: __________ days × Salary / 22 = __________
EMP002: __________ days × Salary / 22 = __________
EMP003: __________ days × Salary / 22 = __________

All tests completed? ☐ YES ☐ NO
```

---

## Checklist Execution

### Pre-Testing Checklist

- [ ] Environment setup complete (XAMPP, Python, dependencies)
- [ ] Test database created and populated
- [ ] Test employees registered (EMP001-EMP005)
- [ ] Face enrollment completed for all test employees
- [ ] Webcam tested and working
- [ ] Geofence location verified
- [ ] All test data backed up
- [ ] Team briefed on testing procedures

### During Testing Checklist

- [ ] All test cases documented with actual results
- [ ] Screenshots captured for critical steps
- [ ] Failures logged with exact error messages
- [ ] Database states verified after major operations
- [ ] Timestamps recorded for attendance tests
- [ ] Performance notes documented

### Post-Testing Checklist

- [ ] All test cases executed (100% coverage)
- [ ] Failed tests investigated and documented
- [ ] Results compiled into final report
- [ ] Defects logged with severity levels
- [ ] Database backup taken post-testing
- [ ] Sign-off obtained from stakeholders

---

## Reporting & Documentation

### Test Execution Report Template

```
TEST EXECUTION REPORT
====================

Project: VeriFace HRIS Biometric Pro
Test Date: _________________
Tester Name: _________________
Test Environment: Windows 11, XAMPP 8.0, Python 3.10

SUMMARY:
--------
Total Test Cases: 15
Passed: _____
Failed: _____
Blocked: _____
Pass Rate: _____%

DETAILED RESULTS:
-----------------
[List each test case with status]

CRITICAL DEFECTS FOUND:
-----------------------
[List any critical/high severity issues]

KNOWN ISSUES/LIMITATIONS:
------------------------
[Any limitations discovered during testing]

RECOMMENDATIONS:
----------------
[Suggestions for improvement]

SIGN-OFF:
---------
Tester: _________________ Date: _______
QA Lead: _________________ Date: _______
Project Manager: _________________ Date: _______
```

### Defect Logging Template

```
DEFECT REPORT
=============

Defect ID: DEF-001
Title: [Brief description]
Severity: CRITICAL / HIGH / MEDIUM / LOW
Status: OPEN / IN PROGRESS / FIXED / CLOSED

Description:
[Detailed description of the issue]

Steps to Reproduce:
1. [Step 1]
2. [Step 2]
3. [Step 3]

Expected Result:
[What should happen]

Actual Result:
[What actually happened]

Attachments:
- Screenshot
- Database logs
- Browser console error

Assigned To: [Developer]
Target Fix Date: [Date]
```

### Failure Evidence Documentation

For each failed test, capture:
1. **Screenshot** of the failure state
2. **Browser Console** error messages (F12 → Console)
3. **Database Query** showing incorrect data
4. **Server Logs** from Flask API (`app.py` output)
5. **Timestamp** of failure
6. **Reproducibility** (consistently fails / intermittent)

---

## Appendix A: Database Test Queries

### Verify Attendance Records
```sql
-- Check today's attendance
SELECT employee_id, check_in, check_out, status 
FROM attendance 
WHERE DATE(date) = CURDATE()
ORDER BY employee_id;

-- Check for late arrivals
SELECT * FROM attendance 
WHERE status = 'LATE' 
AND MONTH(date) = MONTH(CURDATE());

-- Check for absences
SELECT * FROM attendance 
WHERE status = 'ABSENT' 
AND MONTH(date) = MONTH(CURDATE());
```

### Verify Payroll Data
```sql
-- Check payroll calculations
SELECT 
    employee_id,
    DATE_FORMAT(payroll_date, '%Y-%m') as month,
    gross_salary,
    tax_deduction,
    insurance_deduction,
    net_salary,
    approval_status
FROM payroll
WHERE DATE_FORMAT(payroll_date, '%Y-%m') = DATE_FORMAT(CURDATE(), '%Y-%m');

-- Verify deductions applied
SELECT 
    employee_id,
    SUM(amount) as total_deductions
FROM deductions
WHERE MONTH(deduction_date) = MONTH(CURDATE())
GROUP BY employee_id;
```

### Verify Face Recognition Data
```sql
-- Check face enrollment status
SELECT employee_id, enroll_date, face_count, status 
FROM face_enrollments;

-- Check face matching logs
SELECT employee_id, attempt_time, confidence, result 
FROM face_logs 
WHERE DATE(attempt_time) = CURDATE()
ORDER BY attempt_time DESC;

-- Check spoofing attempts
SELECT * FROM spoof_logs ORDER BY detected_time DESC;
```

---

## Appendix B: Manual Calculation Examples

### Example 1: Basic Salary Calculation
```
Employee: EMP001
Monthly Salary: Rp 10,000,000
Working Days (Oct 2026): 22 days
Attendance: 20 days (2 days absent)

Calculation:
Daily Rate = 10,000,000 / 22 = Rp 454,545/day
Actual Pay = 454,545 × 20 = Rp 9,090,900

Deductions:
- Tax (10%): 9,090,900 × 0.10 = Rp 909,090
- Insurance: Rp 100,000
- Total Deductions: Rp 1,009,090

Net Salary = 9,090,900 - 1,009,090 = Rp 8,081,810
```

### Example 2: Overtime Calculation
```
Employee: EMP002
Overtime Hours: 10 hours
Overtime Rate: 1.5x
Hourly Rate: 8,000,000 / 22 / 8 = Rp 45,455/hour

Overtime Payment: 45,455 × 1.5 × 10 = Rp 681,825

Added to gross salary before deductions
```

### Example 3: Late Deduction
```
Employee: EMP003
Late Days: 2 days (30 minutes each)
Daily Rate: 9,000,000 / 22 = Rp 409,091/day
Late Deduction Rate: 10% of daily rate

Late Deduction = 409,091 × 0.10 × 2 = Rp 81,818

Subtracted from gross salary
```

---

## Test Execution Notes

**Tester Name:** _______________________  
**Date Started:** _______________________  
**Date Completed:** _______________________  
**Environment:** _______________________  
**Notes:**
```

[Use this space for additional observations]

```

---

**Document Version:** 1.0  
**Last Updated:** 2026-10-16  
**Next Review:** 2026-11-16
