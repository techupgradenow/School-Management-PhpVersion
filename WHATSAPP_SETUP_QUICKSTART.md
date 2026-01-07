# WhatsApp Integration - Quick Setup Guide

## ✅ What's Been Implemented

### Frontend (UI)
1. ✅ **Student Form** - WhatsApp number field added
2. ✅ **Attendance Modal** - WhatsApp checkbox (green branded)
3. ✅ **Enhanced Dropdown** - Multiple send-to options
4. ✅ **JavaScript Integration** - API calls to backend

### Backend (API)
1. ✅ **whatsapp.php** - Complete API handler
2. ✅ **Database Migration** - SQL script for new fields
3. ✅ **Notification Logging** - Track all sent messages

---

## 🚀 Quick Setup (5 Minutes)

### Step 1: Run Database Migration

Open phpMyAdmin or MySQL command line and run:

```bash
mysql -u root -p edumanage_pro < database/migrations/add_whatsapp_fields.sql
```

Or manually execute in phpMyAdmin:
```sql
ALTER TABLE students
ADD COLUMN whatsapp_number VARCHAR(20) NULL
AFTER contact_number;

CREATE INDEX idx_whatsapp_number ON students(whatsapp_number);

CREATE TABLE notification_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    student_id INT NOT NULL,
    type ENUM('sms', 'email', 'whatsapp', 'app') NOT NULL,
    recipient VARCHAR(100) NOT NULL,
    message TEXT NOT NULL,
    status ENUM('sent', 'failed', 'pending') DEFAULT 'pending',
    error_message TEXT NULL,
    sent_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (student_id) REFERENCES students(id) ON DELETE CASCADE
);
```

### Step 2: Choose WhatsApp Service Provider

**Option A: Twilio (Recommended for Testing)**
1. Sign up at: https://www.twilio.com/try-twilio
2. Get your credentials:
   - Account SID
   - Auth Token
   - WhatsApp Sandbox Number

**Option B: WhatsApp Business API**
1. Apply at: https://business.whatsapp.com
2. Complete Facebook Business verification
3. Get API credentials

**Option C: Gupshup (India-focused)**
1. Sign up at: https://www.gupshup.io
2. Get API key
3. Verify WhatsApp Business number

### Step 3: Configure API Credentials

Edit `backend/api/whatsapp.php`:

```php
// Line 13-15: Update with your Twilio credentials
define('TWILIO_SID', 'YOUR_ACCOUNT_SID_HERE');
define('TWILIO_TOKEN', 'YOUR_AUTH_TOKEN_HERE');
define('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886'); // Your number

// OR for alternative service, set line 18:
define('USE_TWILIO', false); // Then configure alternative service
```

### Step 4: Test the Integration

1. **Add Test Student**:
   - Go to: Students > Add Student
   - Fill in details
   - **Important**: Add WhatsApp number with country code: `+919876543210`

2. **Mark Attendance**:
   - Go to: Attendance > Select Class & Section
   - Mark student as Present/Absent/Late

3. **Send Test Notification**:
   - Click "Notify Parents" button
   - Check WhatsApp checkbox ✅
   - Select "Absent Students' Parents Only"
   - Click "Send Notifications"

4. **Verify Message Received**:
   - Check parent's WhatsApp for message
   - Check console for any errors

---

## 📱 Twilio Setup (Detailed)

### Free Tier Limitations:
- $15.50 free credit
- WhatsApp Sandbox (test mode)
- Must join sandbox via WhatsApp first

### Setup Steps:

1. **Create Twilio Account**:
   ```
   https://www.twilio.com/try-twilio
   ```

2. **Get Credentials**:
   - Dashboard > Account Info
   - Copy Account SID
   - Copy Auth Token

3. **Activate WhatsApp Sandbox**:
   - Dashboard > Messaging > Try WhatsApp
   - Send "join [your-code]" to sandbox number
   - Example: Send "join amazing-tiger" to +1 415 523 8886

4. **Get Sandbox Number**:
   - Copy the WhatsApp-enabled number
   - Format: `whatsapp:+14155238886`

5. **Update PHP File**:
   ```php
   define('TWILIO_SID', 'ACxxxxxxxxxxxxxxxxxxxxx');
   define('TWILIO_TOKEN', 'your_auth_token_here');
   define('TWILIO_WHATSAPP_NUMBER', 'whatsapp:+14155238886');
   ```

6. **Test API**:
   ```bash
   # Use Postman or curl
   curl -X POST http://localhost/School-Management-PhpVersion/backend/api/whatsapp.php \
     -H "Content-Type: application/json" \
     -d '{
       "action": "test_whatsapp",
       "test_number": "+919876543210"
     }'
   ```

---

## 🔧 Troubleshooting

### Issue 1: "Invalid phone number format"
**Solution**: Ensure number has country code
```
❌ Wrong: 9876543210
✅ Correct: +919876543210
```

### Issue 2: "Authentication failed"
**Solution**: Check Twilio credentials
```php
// Verify these are correct
define('TWILIO_SID', 'AC...');  // Starts with AC
define('TWILIO_TOKEN', '...');   // 32 characters
```

### Issue 3: "Message not received"
**Solution**:
1. Check if recipient joined Twilio sandbox
2. Verify WhatsApp number is active
3. Check notification_logs table for errors

### Issue 4: "Database table not found"
**Solution**: Run migration SQL script
```sql
-- Check if table exists
SHOW TABLES LIKE 'notification_logs';

-- If not, create it
SOURCE database/migrations/add_whatsapp_fields.sql;
```

### Issue 5: "CORS error in browser"
**Solution**: Add CORS headers (already added in whatsapp.php)
```php
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
```

---

## 📊 Monitoring & Logs

### Check Sent Messages:
```sql
SELECT
    nl.id,
    s.name AS student_name,
    nl.recipient AS whatsapp_number,
    nl.message,
    nl.status,
    nl.sent_at
FROM notification_logs nl
JOIN students s ON nl.student_id = s.id
WHERE nl.type = 'whatsapp'
ORDER BY nl.sent_at DESC
LIMIT 20;
```

### Check Failed Messages:
```sql
SELECT * FROM notification_logs
WHERE type = 'whatsapp' AND status = 'failed'
ORDER BY created_at DESC;
```

### Count Messages by Status:
```sql
SELECT status, COUNT(*) as count
FROM notification_logs
WHERE type = 'whatsapp'
GROUP BY status;
```

---

## 💰 Cost Estimate (Twilio)

### Pricing:
- **WhatsApp (User-initiated)**: $0.005 per message
- **WhatsApp (Business-initiated)**: $0.0042-0.0068 per message

### Monthly Estimate (1000 students):
- Daily absent notifications: ~100 messages/day
- Cost per day: $0.50
- Monthly cost: $11-15
- Yearly cost: ~$132-180

### Cost Optimization:
1. Only send to absent students (reduces by 85%)
2. Batch messages during off-peak hours
3. Use session messages (lower cost)
4. Weekly summaries instead of daily

---

## 🎯 Testing Checklist

- [ ] Database migration completed
- [ ] Twilio/API credentials configured
- [ ] Test student added with WhatsApp number
- [ ] Attendance marked for test student
- [ ] WhatsApp notification sent successfully
- [ ] Message received on parent's phone
- [ ] Notification logged in database
- [ ] Error handling works (invalid number)
- [ ] Multiple students tested
- [ ] Different attendance types tested (Present/Absent/Late)

---

## 🔐 Security Best Practices

1. **API Credentials**:
   - Store in environment variables (not in code)
   - Use `.env` file
   - Add `.env` to `.gitignore`

2. **Phone Number Validation**:
   - Already implemented in whatsapp.php
   - Regex: `/^\+[1-9]\d{1,14}$/`

3. **Rate Limiting**:
   - Add 100ms delay between messages
   - Already implemented: `usleep(100000)`

4. **Data Privacy**:
   - Get parent consent before sending
   - Provide opt-out option
   - Comply with data protection laws

5. **Error Logging**:
   - All errors logged to notification_logs table
   - Check logs regularly

---

## 📞 Support

### Official Documentation:
- **Twilio WhatsApp API**: https://www.twilio.com/docs/whatsapp
- **WhatsApp Business API**: https://developers.facebook.com/docs/whatsapp
- **Gupshup**: https://www.gupshup.io/developer/docs

### Common Questions:

**Q: Can I use my personal WhatsApp number?**
A: No, you need WhatsApp Business API approval.

**Q: Is Twilio Sandbox free?**
A: Yes, with $15.50 free credit for testing.

**Q: How do I go to production?**
A: Request WhatsApp Business Account from Twilio or Facebook.

**Q: What's the message limit?**
A: Twilio: 1,000 messages/day (sandbox), unlimited (production)

**Q: Can I send images/PDFs?**
A: Yes, WhatsApp API supports media (update PHP code accordingly)

---

## 🎉 Next Steps

Once basic integration works:

1. **Add Message Templates**: Create reusable templates
2. **Schedule Messages**: Send at optimal times
3. **Two-way Communication**: Receive replies from parents
4. **Rich Media**: Send attendance reports as PDFs
5. **Chatbot**: Auto-respond to common queries
6. **Analytics Dashboard**: Track delivery rates
7. **Multi-language Support**: Hindi, regional languages

---

**Last Updated**: January 7, 2026
**Version**: 1.0.0
**Status**: ✅ Ready for Testing
