# WhatsApp Integration Guide - Attendance Notifications

## Overview
The School Management System now supports sending attendance notifications directly to parents' WhatsApp numbers. This feature integrates with the Student Management and Attendance modules.

---

## 1. Student Form Enhancement

### WhatsApp Number Field Added

**Location**: Student Management > Add/Edit Student Form

**New Field**:
- **Field Name**: WhatsApp Number
- **ID**: `studentWhatsapp`
- **Icon**: WhatsApp logo (green #25D366)
- **Format**: International format with country code (+91XXXXXXXXXX)
- **Helper Text**: "Format: +91XXXXXXXXXX (Include country code for WhatsApp)"

**Features**:
- ✅ Green WhatsApp branding for easy identification
- ✅ Country code format guidance
- ✅ Optional field (not required for student creation)
- ✅ Stored in database for future notifications

**Form Layout**:
```
Row 3: Guardian Contact Information
├── Parent/Guardian Name
├── Phone Number
├── WhatsApp Number ⭐ NEW
├── Email
└── Aadhaar Number
```

---

## 2. Attendance Notification Modal Enhancement

### WhatsApp Notification Option

**Location**: Attendance > Mark Attendance > Notify Parents Button

**New Checkbox**:
- **Label**: WhatsApp
- **ID**: `notifyWhatsapp`
- **Default**: Checked (enabled by default)
- **Styling**: Green border (#25D366) to match WhatsApp branding

**Notification Type Options**:
1. ✅ SMS
2. ✅ EMAIL
3. ☑️ APP NOTIFICATION
4. ✅ **WhatsApp** ⭐ NEW (Default: Checked)

---

## 3. Send To Dropdown Enhancement

### Additional Target Options

**New Options Available**:
1. **All Parents (Everyone)** - Send to all parents regardless of attendance
2. **Present Students' Parents Only** - NEW option for present students
3. **Absent Students' Parents Only** - ⭐ Default selection (most common)
4. **Late Students' Parents Only** - For late arrivals
5. **Custom Selection (Advanced)** - Future feature for manual selection

**Visual Enhancements**:
- 👥 Users icon in label
- ℹ️ Helper text: "Select the target audience for this notification"
- Purple accent color for icons
- Dropdown with custom arrow styling

---

## 4. Backend Integration Required

### Database Schema Update

**Students Table** - Add new column:
```sql
ALTER TABLE students
ADD COLUMN whatsapp_number VARCHAR(20) NULL
AFTER contact_number;
```

### API Endpoint Required

**File**: `backend/api/whatsapp.php`

```php
<?php
require_once '../config/db.php';
require_once '../helpers/functions.php';

// WhatsApp API Integration
function sendWhatsAppMessage($phoneNumber, $message) {
    // Option 1: Twilio WhatsApp API
    $twilioSid = 'YOUR_TWILIO_SID';
    $twilioToken = 'YOUR_TWILIO_TOKEN';
    $twilioWhatsAppNumber = 'whatsapp:+14155238886'; // Twilio sandbox

    $url = "https://api.twilio.com/2010-04-01/Accounts/$twilioSid/Messages.json";

    $data = [
        'From' => $twilioWhatsAppNumber,
        'To' => 'whatsapp:' . $phoneNumber,
        'Body' => $message
    ];

    $ch = curl_init($url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
    curl_setopt($ch, CURLOPT_USERPWD, "$twilioSid:$twilioToken");

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return $httpCode === 201;
}

// Handle attendance notification request
if ($_POST['action'] === 'send_whatsapp_notifications') {
    $targetType = $_POST['target'] ?? 'absent';
    $message = $_POST['message'];

    // Get students based on target type
    $students = getStudentsByAttendanceStatus($targetType);

    $sent = 0;
    $failed = 0;

    foreach ($students as $student) {
        if (!empty($student['whatsapp_number'])) {
            $personalizedMessage = str_replace(
                ['{STATUS}', '{DATE}', '{STUDENT_NAME}'],
                [$student['status'], date('d-m-Y'), $student['name']],
                $message
            );

            if (sendWhatsAppMessage($student['whatsapp_number'], $personalizedMessage)) {
                $sent++;
            } else {
                $failed++;
            }
        }
    }

    echo json_encode([
        'success' => true,
        'sent' => $sent,
        'failed' => $failed
    ]);
}
?>
```

---

## 5. WhatsApp Service Providers

### Recommended Options:

### **Option 1: Twilio (Recommended)**
- **Cost**: Pay-as-you-go ($0.005 per message)
- **Setup**: Easy, well-documented API
- **Features**: Delivery reports, templates
- **Website**: https://www.twilio.com/whatsapp

### **Option 2: WhatsApp Business API**
- **Cost**: Free (but requires Facebook Business verification)
- **Setup**: Complex, requires business verification
- **Features**: Official WhatsApp branding, templates
- **Website**: https://business.whatsapp.com

### **Option 3: Gupshup**
- **Cost**: Competitive pricing for India
- **Setup**: Moderate, good for India market
- **Features**: Templates, campaigns
- **Website**: https://www.gupshup.io

### **Option 4: Aisensy (India-focused)**
- **Cost**: ₹0.35 per message (approx)
- **Setup**: Easy, India-specific
- **Features**: Templates, chatbots
- **Website**: https://www.aisensy.com

---

## 6. Message Template Examples

### Absent Notification:
```
Dear Parent,

Your child {STUDENT_NAME} (Roll No: {ROLL_NO}) was marked ABSENT on {DATE}.

If this is incorrect or if there's a valid reason, please contact the school office.

- EduManage Pro
School Contact: +91-XXXXXXXXXX
```

### Late Notification:
```
Dear Parent,

{STUDENT_NAME} arrived late to school today ({DATE}) at {TIME}.

Please ensure punctuality for better learning outcomes.

Thank you,
EduManage Pro
```

### Present Notification:
```
Dear Parent,

{STUDENT_NAME} is present at school today ({DATE}).

Have a great day!

- EduManage Pro
```

---

## 7. Implementation Steps

### Step 1: Update Database
```sql
-- Add WhatsApp column to students table
ALTER TABLE students
ADD COLUMN whatsapp_number VARCHAR(20) NULL
AFTER contact_number;

-- Add index for faster lookups
CREATE INDEX idx_whatsapp ON students(whatsapp_number);
```

### Step 2: Update Student API
**File**: `backend/api/students.php`

Add WhatsApp field to INSERT and UPDATE queries:
```php
// In save student function
$whatsapp = $_POST['whatsapp_number'] ?? null;

// INSERT query
$stmt = $pdo->prepare("
    INSERT INTO students (..., whatsapp_number)
    VALUES (..., ?)
");
$stmt->execute([..., $whatsapp]);
```

### Step 3: Create WhatsApp API Integration
Create `backend/api/whatsapp.php` (see section 4 above)

### Step 4: Update Frontend JavaScript
**File**: `frontend/pages/attendance.html`

Add WhatsApp handling to notification send function:
```javascript
async function sendNotifications() {
    const whatsappEnabled = document.getElementById('notifyWhatsapp').checked;

    if (whatsappEnabled) {
        const result = await apiCall('whatsapp.php', {
            method: 'POST',
            body: JSON.stringify({
                action: 'send_whatsapp_notifications',
                target: notifyTarget.value,
                message: notifyMessage.value,
                date: dateSelect.value,
                class: classSelect.value,
                section: sectionSelect.value
            })
        });

        if (result.success) {
            toast(`WhatsApp sent: ${result.sent}, Failed: ${result.failed}`, 'success');
        }
    }
}
```

### Step 5: Test the Integration
1. Add a student with WhatsApp number (+91XXXXXXXXXX)
2. Mark attendance (Absent/Late/Present)
3. Click "Notify Parents"
4. Select target type and enable WhatsApp
5. Send notifications
6. Verify message received on WhatsApp

---

## 8. User Interface Updates

### Students Form
![WhatsApp Field](attachment:whatsapp-field.png)
- Green WhatsApp icon
- Clear format instructions
- Optional field

### Attendance Modal
![Notification Modal](attachment:notification-modal.png)
- WhatsApp checkbox (default: checked)
- Green border for WhatsApp branding
- Enhanced dropdown with more options

---

## 9. Security Considerations

### Phone Number Validation
```javascript
function validateWhatsAppNumber(number) {
    // Must start with + and country code
    const regex = /^\+[1-9]\d{1,14}$/;
    return regex.test(number);
}
```

### Rate Limiting
```php
// Limit to 100 messages per minute
$rateLimiter = new RateLimiter('whatsapp', 100, 60);
if (!$rateLimiter->allow()) {
    throw new Exception('Rate limit exceeded');
}
```

### Data Privacy
- Store WhatsApp numbers encrypted
- Comply with GDPR/data protection laws
- Obtain parent consent for WhatsApp communication

---

## 10. Cost Estimation

### Twilio Pricing (Example):
- **Message Cost**: $0.005 per message
- **Monthly for 1000 students**:
  - Daily attendance notifications: 1000 students × $0.005 = $5/day
  - Monthly (22 working days): $110/month
  - Yearly: ~$1,320/year

### Cost Optimization:
1. Only send to absent/late students (reduces by 80-90%)
2. Weekly summaries instead of daily
3. Batch messages during off-peak hours

---

## 11. Troubleshooting

### Common Issues:

**Issue 1**: WhatsApp number not saving
- **Solution**: Check database column exists and API is sending the field

**Issue 2**: Messages not delivered
- **Solution**: Verify phone number format (+91XXXXXXXXXX), check API credentials

**Issue 3**: High failure rate
- **Solution**: Validate numbers before sending, check for invalid formats

**Issue 4**: Rate limit errors
- **Solution**: Implement queue system, send in batches

---

## 12. Future Enhancements

### Planned Features:
1. ✅ Custom message templates
2. ✅ Attendance reports via WhatsApp
3. ✅ Fee payment reminders
4. ✅ Exam result notifications
5. ✅ Event announcements
6. ✅ Two-way communication (replies)
7. ✅ Chatbot for common queries

---

## Support

For questions or issues:
- Email: support@edumanagepro.com
- Documentation: https://docs.edumanagepro.com
- GitHub: https://github.com/yourusername/school-management

---

**Last Updated**: January 7, 2026
**Version**: 2.0.0
