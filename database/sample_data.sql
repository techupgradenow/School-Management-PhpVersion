-- ========================================
-- EduManage Pro - Sample Data
-- School Management System
-- ========================================

USE edumanage_pro;

-- ========================================
-- CLASSES
-- ========================================
INSERT INTO classes (class_name, capacity) VALUES
('Class 8', 40),
('Class 9', 40),
('Class 10', 45),
('Class 11', 40),
('Class 12', 40);

-- ========================================
-- SECTIONS
-- ========================================
INSERT INTO sections (class_id, section_name, capacity) VALUES
(1, 'A', 40), (1, 'B', 40), (1, 'C', 40),
(2, 'A', 40), (2, 'B', 40), (2, 'C', 40),
(3, 'A', 45), (3, 'B', 45), (3, 'C', 45),
(4, 'A', 40), (4, 'B', 40),
(5, 'A', 40), (5, 'B', 40);

-- ========================================
-- TEACHERS (20 teachers)
-- ========================================
INSERT INTO teachers (id, name, gender, subject, contact, email, qualification, experience, joining_date, salary, status, employee_id, department, designation) VALUES
('TCH001', 'Dr. Rajesh Kumar', 'Male', 'Mathematics', '9876543201', 'rajesh.kumar@edumanage.edu', 'Ph.D. Mathematics', 15, '2015-06-15', 75000.00, 'Active', 'EMP001', 'Science', 'HOD'),
('TCH002', 'Mrs. Priya Sharma', 'Female', 'Physics', '9876543202', 'priya.sharma@edumanage.edu', 'M.Sc. Physics', 12, '2016-07-01', 65000.00, 'Active', 'EMP002', 'Science', 'Senior Teacher'),
('TCH003', 'Mr. Amit Singh', 'Male', 'Chemistry', '9876543203', 'amit.singh@edumanage.edu', 'M.Sc. Chemistry', 10, '2017-04-10', 60000.00, 'Active', 'EMP003', 'Science', 'Senior Teacher'),
('TCH004', 'Mrs. Sunita Verma', 'Female', 'Biology', '9876543204', 'sunita.verma@edumanage.edu', 'M.Sc. Biology', 8, '2018-06-20', 55000.00, 'Active', 'EMP004', 'Science', 'Teacher'),
('TCH005', 'Mr. Vikram Patel', 'Male', 'English', '9876543205', 'vikram.patel@edumanage.edu', 'M.A. English', 14, '2014-08-01', 70000.00, 'Active', 'EMP005', 'Languages', 'HOD'),
('TCH006', 'Mrs. Anjali Gupta', 'Female', 'Hindi', '9876543206', 'anjali.gupta@edumanage.edu', 'M.A. Hindi', 11, '2016-03-15', 58000.00, 'Active', 'EMP006', 'Languages', 'Senior Teacher'),
('TCH007', 'Mr. Deepak Joshi', 'Male', 'History', '9876543207', 'deepak.joshi@edumanage.edu', 'M.A. History', 9, '2017-07-01', 52000.00, 'Active', 'EMP007', 'Humanities', 'Teacher'),
('TCH008', 'Mrs. Kavita Rao', 'Female', 'Geography', '9876543208', 'kavita.rao@edumanage.edu', 'M.A. Geography', 7, '2019-01-10', 48000.00, 'Active', 'EMP008', 'Humanities', 'Teacher'),
('TCH009', 'Mr. Suresh Nair', 'Male', 'Computer Science', '9876543209', 'suresh.nair@edumanage.edu', 'MCA', 13, '2015-02-20', 72000.00, 'Active', 'EMP009', 'Computer', 'HOD'),
('TCH010', 'Mrs. Meera Krishnan', 'Female', 'Economics', '9876543210', 'meera.krishnan@edumanage.edu', 'M.A. Economics', 6, '2020-06-01', 45000.00, 'Active', 'EMP010', 'Commerce', 'Teacher'),
('TCH011', 'Mr. Rahul Mehta', 'Male', 'Accountancy', '9876543211', 'rahul.mehta@edumanage.edu', 'M.Com', 10, '2017-08-15', 58000.00, 'Active', 'EMP011', 'Commerce', 'Senior Teacher'),
('TCH012', 'Mrs. Pooja Saxena', 'Female', 'Political Science', '9876543212', 'pooja.saxena@edumanage.edu', 'M.A. Political Science', 5, '2021-04-01', 42000.00, 'Active', 'EMP012', 'Humanities', 'Teacher'),
('TCH013', 'Mr. Arun Pillai', 'Male', 'Physical Education', '9876543213', 'arun.pillai@edumanage.edu', 'B.P.Ed', 8, '2018-07-10', 40000.00, 'Active', 'EMP013', 'Sports', 'Teacher'),
('TCH014', 'Mrs. Lakshmi Iyer', 'Female', 'Music', '9876543214', 'lakshmi.iyer@edumanage.edu', 'M.A. Music', 12, '2016-01-05', 50000.00, 'Active', 'EMP014', 'Arts', 'Teacher'),
('TCH015', 'Mr. Karthik Reddy', 'Male', 'Art', '9876543215', 'karthik.reddy@edumanage.edu', 'B.F.A', 4, '2022-06-15', 38000.00, 'Active', 'EMP015', 'Arts', 'Teacher'),
('TCH016', 'Mrs. Nisha Agarwal', 'Female', 'Sanskrit', '9876543216', 'nisha.agarwal@edumanage.edu', 'M.A. Sanskrit', 9, '2018-03-20', 48000.00, 'Active', 'EMP016', 'Languages', 'Teacher'),
('TCH017', 'Mr. Pranav Desai', 'Male', 'Mathematics', '9876543217', 'pranav.desai@edumanage.edu', 'M.Sc. Mathematics', 3, '2023-07-01', 42000.00, 'Active', 'EMP017', 'Science', 'Teacher'),
('TCH018', 'Mrs. Sneha Bhatt', 'Female', 'Physics', '9876543218', 'sneha.bhatt@edumanage.edu', 'M.Sc. Physics', 2, '2024-01-10', 40000.00, 'Active', 'EMP018', 'Science', 'Teacher'),
('TCH019', 'Mr. Vishal Chauhan', 'Male', 'Chemistry', '9876543219', 'vishal.chauhan@edumanage.edu', 'M.Sc. Chemistry', 6, '2020-08-01', 52000.00, 'Active', 'EMP019', 'Science', 'Teacher'),
('TCH020', 'Mrs. Rekha Mishra', 'Female', 'Biology', '9876543220', 'rekha.mishra@edumanage.edu', 'M.Sc. Biology', 4, '2022-02-15', 45000.00, 'Active', 'EMP020', 'Science', 'Teacher');

-- ========================================
-- SUBJECTS
-- ========================================
INSERT INTO subjects (id, name, code, class, teacher_id, max_marks, pass_marks) VALUES
('SUB001', 'Mathematics', 'MATH-10', 'Class 10', 'TCH001', 100, 33),
('SUB002', 'Physics', 'PHY-10', 'Class 10', 'TCH002', 100, 33),
('SUB003', 'Chemistry', 'CHEM-10', 'Class 10', 'TCH003', 100, 33),
('SUB004', 'Biology', 'BIO-10', 'Class 10', 'TCH004', 100, 33),
('SUB005', 'English', 'ENG-10', 'Class 10', 'TCH005', 100, 33),
('SUB006', 'Hindi', 'HIN-10', 'Class 10', 'TCH006', 100, 33),
('SUB007', 'History', 'HIST-10', 'Class 10', 'TCH007', 100, 33),
('SUB008', 'Geography', 'GEO-10', 'Class 10', 'TCH008', 100, 33),
('SUB009', 'Computer Science', 'CS-10', 'Class 10', 'TCH009', 100, 33),
('SUB010', 'Mathematics', 'MATH-12', 'Class 12', 'TCH001', 100, 33),
('SUB011', 'Physics', 'PHY-12', 'Class 12', 'TCH002', 100, 33),
('SUB012', 'Chemistry', 'CHEM-12', 'Class 12', 'TCH003', 100, 33);

-- ========================================
-- STUDENTS (50 students)
-- ========================================
INSERT INTO students (id, name, gender, class, section, parent_name, contact, email, address, dob, joining_date, blood_group, status, admission_no, roll_no) VALUES
('STU001', 'Aarav Sharma', 'Male', 'Class 10', 'A', 'Mr. Rakesh Sharma', '9876543301', 'aarav.sharma@email.com', '123 MG Road, Delhi', '2008-05-15', '2020-04-01', 'A+', 'Active', 'ADM2020001', '1'),
('STU002', 'Ananya Patel', 'Female', 'Class 10', 'A', 'Mr. Sunil Patel', '9876543302', 'ananya.patel@email.com', '456 Park Street, Mumbai', '2008-08-22', '2020-04-01', 'B+', 'Active', 'ADM2020002', '2'),
('STU003', 'Arjun Singh', 'Male', 'Class 10', 'A', 'Mr. Harpreet Singh', '9876543303', 'arjun.singh@email.com', '789 Civil Lines, Chandigarh', '2008-03-10', '2020-04-01', 'O+', 'Active', 'ADM2020003', '3'),
('STU004', 'Diya Gupta', 'Female', 'Class 10', 'A', 'Mr. Ashok Gupta', '9876543304', 'diya.gupta@email.com', '321 Sector 15, Noida', '2008-11-05', '2020-04-01', 'AB+', 'Active', 'ADM2020004', '4'),
('STU005', 'Kabir Verma', 'Male', 'Class 10', 'A', 'Mr. Rajiv Verma', '9876543305', 'kabir.verma@email.com', '654 Gandhi Nagar, Jaipur', '2008-07-18', '2020-04-01', 'A-', 'Active', 'ADM2020005', '5'),
('STU006', 'Myra Kapoor', 'Female', 'Class 10', 'B', 'Mr. Vijay Kapoor', '9876543306', 'myra.kapoor@email.com', '987 Lake View, Bangalore', '2008-09-25', '2020-04-01', 'B-', 'Active', 'ADM2020006', '1'),
('STU007', 'Reyansh Kumar', 'Male', 'Class 10', 'B', 'Mr. Anil Kumar', '9876543307', 'reyansh.kumar@email.com', '147 Green Park, Chennai', '2008-01-30', '2020-04-01', 'O-', 'Active', 'ADM2020007', '2'),
('STU008', 'Sara Malhotra', 'Female', 'Class 10', 'B', 'Mr. Sanjay Malhotra', '9876543308', 'sara.malhotra@email.com', '258 Marine Drive, Mumbai', '2008-06-12', '2020-04-01', 'A+', 'Active', 'ADM2020008', '3'),
('STU009', 'Vihaan Joshi', 'Male', 'Class 10', 'B', 'Mr. Prakash Joshi', '9876543309', 'vihaan.joshi@email.com', '369 Hill Road, Pune', '2008-04-08', '2020-04-01', 'B+', 'Active', 'ADM2020009', '4'),
('STU010', 'Zara Khan', 'Female', 'Class 10', 'B', 'Mr. Imran Khan', '9876543310', 'zara.khan@email.com', '741 Old City, Hyderabad', '2008-12-20', '2020-04-01', 'AB-', 'Active', 'ADM2020010', '5'),
('STU011', 'Aditya Rao', 'Male', 'Class 10', 'C', 'Mr. Venkat Rao', '9876543311', 'aditya.rao@email.com', '852 Tech Park, Bangalore', '2008-02-14', '2020-04-01', 'O+', 'Active', 'ADM2020011', '1'),
('STU012', 'Ishita Nair', 'Female', 'Class 10', 'C', 'Mr. Krishna Nair', '9876543312', 'ishita.nair@email.com', '963 Beach Road, Kochi', '2008-10-03', '2020-04-01', 'A+', 'Active', 'ADM2020012', '2'),
('STU013', 'Krish Mehta', 'Male', 'Class 9', 'A', 'Mr. Paresh Mehta', '9876543313', 'krish.mehta@email.com', '159 Market Street, Ahmedabad', '2009-05-20', '2021-04-01', 'B+', 'Active', 'ADM2021001', '1'),
('STU014', 'Navya Reddy', 'Female', 'Class 9', 'A', 'Mr. Suresh Reddy', '9876543314', 'navya.reddy@email.com', '357 MG Road, Hyderabad', '2009-08-15', '2021-04-01', 'O+', 'Active', 'ADM2021002', '2'),
('STU015', 'Pranav Iyer', 'Male', 'Class 9', 'A', 'Mr. Raman Iyer', '9876543315', 'pranav.iyer@email.com', '486 Temple Street, Chennai', '2009-03-28', '2021-04-01', 'A-', 'Active', 'ADM2021003', '3'),
('STU016', 'Riya Agarwal', 'Female', 'Class 9', 'A', 'Mr. Mohan Agarwal', '9876543316', 'riya.agarwal@email.com', '624 Industrial Area, Lucknow', '2009-11-10', '2021-04-01', 'B-', 'Active', 'ADM2021004', '4'),
('STU017', 'Shaurya Bose', 'Male', 'Class 9', 'B', 'Mr. Amit Bose', '9876543317', 'shaurya.bose@email.com', '753 College Street, Kolkata', '2009-07-05', '2021-04-01', 'AB+', 'Active', 'ADM2021005', '1'),
('STU018', 'Trisha Das', 'Female', 'Class 9', 'B', 'Mr. Subir Das', '9876543318', 'trisha.das@email.com', '891 Salt Lake, Kolkata', '2009-09-18', '2021-04-01', 'O-', 'Active', 'ADM2021006', '2'),
('STU019', 'Yash Pandey', 'Male', 'Class 9', 'B', 'Mr. Vivek Pandey', '9876543319', 'yash.pandey@email.com', '234 Hazratganj, Lucknow', '2009-01-22', '2021-04-01', 'A+', 'Active', 'ADM2021007', '3'),
('STU020', 'Anvi Choudhary', 'Female', 'Class 9', 'B', 'Mr. Dinesh Choudhary', '9876543320', 'anvi.choudhary@email.com', '567 Model Town, Delhi', '2009-06-30', '2021-04-01', 'B+', 'Active', 'ADM2021008', '4'),
('STU021', 'Dhruv Saxena', 'Male', 'Class 12', 'A', 'Mr. Arvind Saxena', '9876543321', 'dhruv.saxena@email.com', '789 Sector 22, Gurgaon', '2006-04-12', '2018-04-01', 'O+', 'Active', 'ADM2018001', '1'),
('STU022', 'Kiara Singhania', 'Female', 'Class 12', 'A', 'Mr. Rajesh Singhania', '9876543322', 'kiara.singhania@email.com', '135 Juhu Beach, Mumbai', '2006-08-25', '2018-04-01', 'A+', 'Active', 'ADM2018002', '2'),
('STU023', 'Arnav Bhargava', 'Male', 'Class 12', 'A', 'Mr. Sudhir Bhargava', '9876543323', 'arnav.bhargava@email.com', '246 Civil Lines, Allahabad', '2006-02-18', '2018-04-01', 'B+', 'Active', 'ADM2018003', '3'),
('STU024', 'Pari Chawla', 'Female', 'Class 12', 'A', 'Mr. Rohit Chawla', '9876543324', 'pari.chawla@email.com', '468 Defence Colony, Delhi', '2006-10-08', '2018-04-01', 'AB+', 'Active', 'ADM2018004', '4'),
('STU025', 'Vivaan Ahuja', 'Male', 'Class 12', 'B', 'Mr. Sandeep Ahuja', '9876543325', 'vivaan.ahuja@email.com', '579 Connaught Place, Delhi', '2006-05-30', '2018-04-01', 'A-', 'Active', 'ADM2018005', '1'),
('STU026', 'Aanya Sethi', 'Female', 'Class 12', 'B', 'Mr. Manish Sethi', '9876543326', 'aanya.sethi@email.com', '680 Lajpat Nagar, Delhi', '2006-12-15', '2018-04-01', 'B-', 'Active', 'ADM2018006', '2'),
('STU027', 'Atharv Thakur', 'Male', 'Class 11', 'A', 'Mr. Ramesh Thakur', '9876543327', 'atharv.thakur@email.com', '791 Shimla Hills, Shimla', '2007-03-22', '2019-04-01', 'O+', 'Active', 'ADM2019001', '1'),
('STU028', 'Saanvi Bansal', 'Female', 'Class 11', 'A', 'Mr. Gopal Bansal', '9876543328', 'saanvi.bansal@email.com', '802 Rajouri Garden, Delhi', '2007-07-14', '2019-04-01', 'A+', 'Active', 'ADM2019002', '2'),
('STU029', 'Rudra Tripathi', 'Male', 'Class 11', 'A', 'Mr. Shyam Tripathi', '9876543329', 'rudra.tripathi@email.com', '913 Varanasi Ghats, Varanasi', '2007-09-08', '2019-04-01', 'B+', 'Active', 'ADM2019003', '3'),
('STU030', 'Avni Jain', 'Female', 'Class 11', 'A', 'Mr. Hemant Jain', '9876543330', 'avni.jain@email.com', '024 Indore Central, Indore', '2007-11-25', '2019-04-01', 'AB-', 'Active', 'ADM2019004', '4'),
('STU031', 'Ayaan Malik', 'Male', 'Class 8', 'A', 'Mr. Farhan Malik', '9876543331', 'ayaan.malik@email.com', '135 Aligarh Road, Aligarh', '2010-04-18', '2022-04-01', 'O+', 'Active', 'ADM2022001', '1'),
('STU032', 'Shanaya Pillai', 'Female', 'Class 8', 'A', 'Mr. Ajay Pillai', '9876543332', 'shanaya.pillai@email.com', '246 Trivandrum City, Trivandrum', '2010-06-22', '2022-04-01', 'A+', 'Active', 'ADM2022002', '2'),
('STU033', 'Lakshya Tiwari', 'Male', 'Class 8', 'A', 'Mr. Mahesh Tiwari', '9876543333', 'lakshya.tiwari@email.com', '357 Bhopal Lake, Bhopal', '2010-08-30', '2022-04-01', 'B+', 'Active', 'ADM2022003', '3'),
('STU034', 'Aadhya Menon', 'Female', 'Class 8', 'B', 'Mr. Rajeev Menon', '9876543334', 'aadhya.menon@email.com', '468 Kochi Port, Kochi', '2010-02-14', '2022-04-01', 'O-', 'Active', 'ADM2022004', '1'),
('STU035', 'Advait Sharma', 'Male', 'Class 8', 'B', 'Mr. Deepak Sharma', '9876543335', 'advait.sharma@email.com', '579 Jaipur Pink City, Jaipur', '2010-10-05', '2022-04-01', 'A-', 'Active', 'ADM2022005', '2'),
('STU036', 'Mishka Roy', 'Female', 'Class 8', 'B', 'Mr. Sourav Roy', '9876543336', 'mishka.roy@email.com', '680 Darjeeling Hills, Darjeeling', '2010-12-28', '2022-04-01', 'B-', 'Active', 'ADM2022006', '3'),
('STU037', 'Rehan Ahmed', 'Male', 'Class 10', 'C', 'Mr. Salim Ahmed', '9876543337', 'rehan.ahmed@email.com', '791 Old Hyderabad, Hyderabad', '2008-05-10', '2020-04-01', 'AB+', 'Active', 'ADM2020013', '3'),
('STU038', 'Tara Oberoi', 'Female', 'Class 10', 'C', 'Mr. Vikram Oberoi', '9876543338', 'tara.oberoi@email.com', '802 South Delhi, Delhi', '2008-07-22', '2020-04-01', 'O+', 'Active', 'ADM2020014', '4'),
('STU039', 'Yuvan Naidu', 'Male', 'Class 9', 'C', 'Mr. Srinivas Naidu', '9876543339', 'yuvan.naidu@email.com', '913 Vizag Beach, Visakhapatnam', '2009-04-15', '2021-04-01', 'A+', 'Active', 'ADM2021009', '1'),
('STU040', 'Amaira Kohli', 'Female', 'Class 9', 'C', 'Mr. Virat Kohli', '9876543340', 'amaira.kohli@email.com', '024 Gurgaon Cyber City, Gurgaon', '2009-09-08', '2021-04-01', 'B+', 'Active', 'ADM2021010', '2'),
('STU041', 'Ishaan Luthra', 'Male', 'Class 11', 'B', 'Mr. Rajan Luthra', '9876543341', 'ishaan.luthra@email.com', '135 Chandigarh Sector 17, Chandigarh', '2007-01-20', '2019-04-01', 'O+', 'Active', 'ADM2019005', '1'),
('STU042', 'Nisha Khanna', 'Female', 'Class 11', 'B', 'Mr. Suresh Khanna', '9876543342', 'nisha.khanna@email.com', '246 Amritsar Golden Temple, Amritsar', '2007-06-12', '2019-04-01', 'A+', 'Active', 'ADM2019006', '2'),
('STU043', 'Aryan Kapoor', 'Male', 'Class 8', 'C', 'Mr. Arun Kapoor', '9876543343', 'aryan.kapoor@email.com', '357 Mumbai Bandra, Mumbai', '2010-03-25', '2022-04-01', 'B+', 'Active', 'ADM2022007', '1'),
('STU044', 'Sia Malhotra', 'Female', 'Class 8', 'C', 'Mr. Rahul Malhotra', '9876543344', 'sia.malhotra@email.com', '468 Delhi Dwarka, Delhi', '2010-11-18', '2022-04-01', 'AB+', 'Active', 'ADM2022008', '2'),
('STU045', 'Veer Singhania', 'Male', 'Class 12', 'B', 'Mr. Amit Singhania', '9876543345', 'veer.singhania@email.com', '579 Pune Koregaon Park, Pune', '2006-08-05', '2018-04-01', 'O-', 'Active', 'ADM2018007', '3'),
('STU046', 'Anaya Chopra', 'Female', 'Class 12', 'B', 'Mr. Yash Chopra', '9876543346', 'anaya.chopra@email.com', '680 Mumbai Juhu, Mumbai', '2006-02-28', '2018-04-01', 'A-', 'Active', 'ADM2018008', '4'),
('STU047', 'Kabir Mehra', 'Male', 'Class 10', 'A', 'Mr. Nikhil Mehra', '9876543347', 'kabir.mehra@email.com', '791 Delhi Vasant Kunj, Delhi', '2008-09-15', '2020-04-01', 'B-', 'Active', 'ADM2020015', '6'),
('STU048', 'Kyra Bhatt', 'Female', 'Class 10', 'B', 'Mr. Shivam Bhatt', '9876543348', 'kyra.bhatt@email.com', '802 Ahmedabad Prahlad Nagar, Ahmedabad', '2008-04-20', '2020-04-01', 'O+', 'Active', 'ADM2020016', '6'),
('STU049', 'Shivansh Mishra', 'Male', 'Class 9', 'A', 'Mr. Alok Mishra', '9876543349', 'shivansh.mishra@email.com', '913 Lucknow Gomti Nagar, Lucknow', '2009-12-10', '2021-04-01', 'A+', 'Active', 'ADM2021011', '5'),
('STU050', 'Anika Sinha', 'Female', 'Class 9', 'B', 'Mr. Pradeep Sinha', '9876543350', 'anika.sinha@email.com', '024 Patna Gandhi Maidan, Patna', '2009-05-28', '2021-04-01', 'B+', 'Active', 'ADM2021012', '5');

-- ========================================
-- ATTENDANCE (Today's attendance for Class 10)
-- ========================================
INSERT INTO attendance (student_id, date, status, remarks, marked_by) VALUES
('STU001', CURDATE(), 'Present', NULL, 'USR001'),
('STU002', CURDATE(), 'Present', NULL, 'USR001'),
('STU003', CURDATE(), 'Absent', 'Sick leave', 'USR001'),
('STU004', CURDATE(), 'Present', NULL, 'USR001'),
('STU005', CURDATE(), 'Present', NULL, 'USR001'),
('STU006', CURDATE(), 'Present', NULL, 'USR001'),
('STU007', CURDATE(), 'Late', 'Arrived 15 mins late', 'USR001'),
('STU008', CURDATE(), 'Present', NULL, 'USR001'),
('STU009', CURDATE(), 'Present', NULL, 'USR001'),
('STU010', CURDATE(), 'Leave', 'Family function', 'USR001'),
('STU011', CURDATE(), 'Present', NULL, 'USR001'),
('STU012', CURDATE(), 'Present', NULL, 'USR001');

-- ========================================
-- FEE STRUCTURES
-- ========================================
INSERT INTO fee_structures (class, fee_type, amount, frequency, description, is_active) VALUES
('Class 8', 'Tuition Fee', 5000.00, 'Monthly', 'Monthly tuition fee for Class 8', TRUE),
('Class 8', 'Library Fee', 500.00, 'Annually', 'Annual library membership', TRUE),
('Class 8', 'Lab Fee', 1000.00, 'Annually', 'Annual laboratory fee', TRUE),
('Class 9', 'Tuition Fee', 5500.00, 'Monthly', 'Monthly tuition fee for Class 9', TRUE),
('Class 9', 'Library Fee', 500.00, 'Annually', 'Annual library membership', TRUE),
('Class 9', 'Lab Fee', 1200.00, 'Annually', 'Annual laboratory fee', TRUE),
('Class 10', 'Tuition Fee', 6000.00, 'Monthly', 'Monthly tuition fee for Class 10', TRUE),
('Class 10', 'Library Fee', 600.00, 'Annually', 'Annual library membership', TRUE),
('Class 10', 'Lab Fee', 1500.00, 'Annually', 'Annual laboratory fee', TRUE),
('Class 10', 'Board Exam Fee', 2500.00, 'One Time', 'CBSE Board examination fee', TRUE),
('Class 11', 'Tuition Fee', 7000.00, 'Monthly', 'Monthly tuition fee for Class 11', TRUE),
('Class 11', 'Library Fee', 700.00, 'Annually', 'Annual library membership', TRUE),
('Class 11', 'Lab Fee', 2000.00, 'Annually', 'Annual laboratory fee', TRUE),
('Class 12', 'Tuition Fee', 7500.00, 'Monthly', 'Monthly tuition fee for Class 12', TRUE),
('Class 12', 'Library Fee', 700.00, 'Annually', 'Annual library membership', TRUE),
('Class 12', 'Lab Fee', 2000.00, 'Annually', 'Annual laboratory fee', TRUE),
('Class 12', 'Board Exam Fee', 3000.00, 'One Time', 'CBSE Board examination fee', TRUE);

-- ========================================
-- FEE PAYMENTS
-- ========================================
INSERT INTO fee_payments (student_id, fee_structure_id, amount_paid, payment_date, payment_mode, receipt_no, status, collected_by) VALUES
('STU001', 7, 6000.00, '2025-12-01', 'Online', 'RCP2025120001', 'Paid', 'USR001'),
('STU001', 8, 600.00, '2025-04-15', 'Cash', 'RCP2025040001', 'Paid', 'USR001'),
('STU002', 7, 6000.00, '2025-12-05', 'Card', 'RCP2025120002', 'Paid', 'USR001'),
('STU003', 7, 6000.00, '2025-12-10', 'Online', 'RCP2025120003', 'Paid', 'USR001'),
('STU004', 7, 3000.00, '2025-12-08', 'Cash', 'RCP2025120004', 'Partial', 'USR001'),
('STU005', 7, 6000.00, '2025-12-02', 'Cheque', 'RCP2025120005', 'Paid', 'USR001'),
('STU006', 7, 6000.00, '2025-12-03', 'Online', 'RCP2025120006', 'Paid', 'USR001'),
('STU021', 14, 7500.00, '2025-12-01', 'Online', 'RCP2025120007', 'Paid', 'USR001'),
('STU022', 14, 7500.00, '2025-12-04', 'Card', 'RCP2025120008', 'Paid', 'USR001'),
('STU023', 14, 7500.00, '2025-12-06', 'Cash', 'RCP2025120009', 'Paid', 'USR001'),
('STU013', 4, 5500.00, '2025-12-02', 'Online', 'RCP2025120010', 'Paid', 'USR001'),
('STU014', 4, 5500.00, '2025-12-07', 'Cash', 'RCP2025120011', 'Paid', 'USR001'),
('STU031', 1, 5000.00, '2025-12-01', 'Online', 'RCP2025120012', 'Paid', 'USR001'),
('STU032', 1, 5000.00, '2025-12-05', 'Card', 'RCP2025120013', 'Paid', 'USR001');

-- ========================================
-- LIBRARY BOOKS
-- ========================================
INSERT INTO library_books (id, title, author, isbn, category, quantity, available, publisher, publication_year, shelf_no, price) VALUES
('BK001', 'Mathematics for Class 10', 'R.D. Sharma', '9781234567890', 'Textbook', 50, 45, 'Dhanpat Rai Publications', 2023, 'A1-01', 450.00),
('BK002', 'Physics NCERT Class 10', 'NCERT', '9781234567891', 'Textbook', 60, 55, 'NCERT', 2023, 'A1-02', 180.00),
('BK003', 'Chemistry NCERT Class 10', 'NCERT', '9781234567892', 'Textbook', 60, 58, 'NCERT', 2023, 'A1-03', 180.00),
('BK004', 'Biology NCERT Class 10', 'NCERT', '9781234567893', 'Textbook', 55, 52, 'NCERT', 2023, 'A1-04', 180.00),
('BK005', 'English Literature', 'Wren & Martin', '9781234567894', 'Textbook', 70, 65, 'S. Chand Publishing', 2022, 'A2-01', 350.00),
('BK006', 'Hindi Vyakaran', 'Vasant Bhag', '9781234567895', 'Textbook', 45, 42, 'NCERT', 2023, 'A2-02', 150.00),
('BK007', 'History of Modern India', 'Bipin Chandra', '9781234567896', 'Reference', 30, 28, 'Orient Blackswan', 2020, 'B1-01', 520.00),
('BK008', 'Indian Geography', 'Majid Husain', '9781234567897', 'Reference', 25, 23, 'McGraw Hill', 2021, 'B1-02', 480.00),
('BK009', 'Computer Fundamentals', 'P.K. Sinha', '9781234567898', 'Textbook', 40, 38, 'BPB Publications', 2022, 'C1-01', 380.00),
('BK010', 'Programming in Python', 'Reema Thareja', '9781234567899', 'Reference', 35, 32, 'Oxford University Press', 2023, 'C1-02', 450.00),
('BK011', 'The Discovery of India', 'Jawaharlal Nehru', '9781234567900', 'General', 20, 18, 'Penguin Books', 2018, 'D1-01', 350.00),
('BK012', 'Wings of Fire', 'A.P.J. Abdul Kalam', '9781234567901', 'Biography', 25, 22, 'Universities Press', 2015, 'D1-02', 280.00),
('BK013', 'The Story of My Experiments with Truth', 'Mahatma Gandhi', '9781234567902', 'Autobiography', 15, 14, 'Navajivan Publishing', 2019, 'D1-03', 320.00),
('BK014', 'A Brief History of Time', 'Stephen Hawking', '9781234567903', 'Science', 20, 17, 'Bantam Books', 2017, 'E1-01', 550.00),
('BK015', 'Sapiens: A Brief History of Humankind', 'Yuval Noah Harari', '9781234567904', 'History', 18, 15, 'Harper Collins', 2020, 'E1-02', 599.00);

-- ========================================
-- LIBRARY ISSUES
-- ========================================
INSERT INTO library_issues (book_id, student_id, issue_date, due_date, return_date, status, fine_amount, issued_by) VALUES
('BK001', 'STU001', '2025-12-01', '2025-12-15', NULL, 'Issued', 0.00, 'USR001'),
('BK005', 'STU002', '2025-12-03', '2025-12-17', NULL, 'Issued', 0.00, 'USR001'),
('BK012', 'STU003', '2025-11-15', '2025-11-29', '2025-11-28', 'Returned', 0.00, 'USR001'),
('BK014', 'STU005', '2025-12-05', '2025-12-19', NULL, 'Issued', 0.00, 'USR001'),
('BK007', 'STU021', '2025-11-20', '2025-12-04', '2025-12-10', 'Returned', 30.00, 'USR001'),
('BK010', 'STU022', '2025-12-08', '2025-12-22', NULL, 'Issued', 0.00, 'USR001'),
('BK015', 'STU013', '2025-12-10', '2025-12-24', NULL, 'Issued', 0.00, 'USR001');

-- ========================================
-- TRANSPORT ROUTES
-- ========================================
INSERT INTO transport_routes (id, route_name, route_no, vehicle_no, driver_name, driver_contact, capacity, fare, status) VALUES
('RT001', 'North Delhi Route', 'R-001', 'DL01AB1234', 'Ramesh Kumar', '9876543401', 40, 2500.00, 'Active'),
('RT002', 'South Delhi Route', 'R-002', 'DL01CD5678', 'Suresh Yadav', '9876543402', 45, 2800.00, 'Active'),
('RT003', 'East Delhi Route', 'R-003', 'DL01EF9012', 'Mahesh Singh', '9876543403', 40, 2400.00, 'Active'),
('RT004', 'West Delhi Route', 'R-004', 'DL01GH3456', 'Dinesh Sharma', '9876543404', 50, 2600.00, 'Active'),
('RT005', 'Gurgaon Route', 'R-005', 'HR01IJ7890', 'Rajesh Verma', '9876543405', 45, 3200.00, 'Active'),
('RT006', 'Noida Route', 'R-006', 'UP01KL2345', 'Vikram Patel', '9876543406', 40, 3000.00, 'Active');

-- ========================================
-- TRANSPORT STOPS
-- ========================================
INSERT INTO transport_stops (route_id, stop_name, stop_order, pickup_time, drop_time) VALUES
('RT001', 'Rohini Sector 3', 1, '07:00:00', '15:30:00'),
('RT001', 'Pitampura', 2, '07:15:00', '15:15:00'),
('RT001', 'Shalimar Bagh', 3, '07:30:00', '15:00:00'),
('RT001', 'Model Town', 4, '07:45:00', '14:45:00'),
('RT002', 'Saket', 1, '07:00:00', '15:30:00'),
('RT002', 'Hauz Khas', 2, '07:15:00', '15:15:00'),
('RT002', 'Green Park', 3, '07:30:00', '15:00:00'),
('RT002', 'Defence Colony', 4, '07:45:00', '14:45:00'),
('RT003', 'Laxmi Nagar', 1, '07:00:00', '15:30:00'),
('RT003', 'Preet Vihar', 2, '07:15:00', '15:15:00'),
('RT003', 'Nirman Vihar', 3, '07:30:00', '15:00:00'),
('RT004', 'Janakpuri', 1, '07:00:00', '15:30:00'),
('RT004', 'Dwarka Sector 10', 2, '07:20:00', '15:10:00'),
('RT004', 'Palam', 3, '07:40:00', '14:50:00'),
('RT005', 'DLF Phase 1', 1, '06:45:00', '15:45:00'),
('RT005', 'Sushant Lok', 2, '07:00:00', '15:30:00'),
('RT005', 'Cyber City', 3, '07:15:00', '15:15:00'),
('RT006', 'Sector 18 Noida', 1, '06:50:00', '15:40:00'),
('RT006', 'Sector 62', 2, '07:10:00', '15:20:00'),
('RT006', 'Greater Noida', 3, '07:30:00', '15:00:00');

-- ========================================
-- TRANSPORT ASSIGNMENTS
-- ========================================
INSERT INTO transport_assignments (student_id, route_id, stop_id, start_date, status) VALUES
('STU001', 'RT001', 1, '2025-04-01', 'Active'),
('STU002', 'RT001', 2, '2025-04-01', 'Active'),
('STU003', 'RT002', 5, '2025-04-01', 'Active'),
('STU004', 'RT002', 6, '2025-04-01', 'Active'),
('STU005', 'RT003', 9, '2025-04-01', 'Active'),
('STU006', 'RT003', 10, '2025-04-01', 'Active'),
('STU021', 'RT005', 15, '2025-04-01', 'Active'),
('STU022', 'RT005', 16, '2025-04-01', 'Active'),
('STU031', 'RT004', 12, '2025-04-01', 'Active'),
('STU032', 'RT006', 18, '2025-04-01', 'Active');

-- ========================================
-- HOSTEL BLOCKS
-- ========================================
INSERT INTO hostel_blocks (id, block_name, block_type, total_rooms, warden_name, warden_contact) VALUES
('BLK001', 'Vivekananda Boys Hostel', 'Boys', 50, 'Mr. Prakash Sharma', '9876543501'),
('BLK002', 'Tagore Boys Hostel', 'Boys', 40, 'Mr. Arun Kumar', '9876543502'),
('BLK003', 'Sarojini Girls Hostel', 'Girls', 45, 'Mrs. Sunita Devi', '9876543503'),
('BLK004', 'Kalpana Girls Hostel', 'Girls', 35, 'Mrs. Meera Sharma', '9876543504');

-- ========================================
-- HOSTEL ROOMS
-- ========================================
INSERT INTO hostel_rooms (id, block_id, room_no, room_type, capacity, occupied, floor, monthly_fee, status) VALUES
('RM001', 'BLK001', '101', 'Double Sharing', 2, 2, 1, 5000.00, 'Full'),
('RM002', 'BLK001', '102', 'Double Sharing', 2, 1, 1, 5000.00, 'Available'),
('RM003', 'BLK001', '103', 'Triple Sharing', 3, 2, 1, 4000.00, 'Available'),
('RM004', 'BLK001', '201', 'Single', 1, 1, 2, 8000.00, 'Full'),
('RM005', 'BLK001', '202', 'Double Sharing', 2, 0, 2, 5000.00, 'Available'),
('RM006', 'BLK002', '101', 'Double Sharing', 2, 2, 1, 5500.00, 'Full'),
('RM007', 'BLK002', '102', 'Triple Sharing', 3, 1, 1, 4500.00, 'Available'),
('RM008', 'BLK003', '101', 'Double Sharing', 2, 2, 1, 5000.00, 'Full'),
('RM009', 'BLK003', '102', 'Double Sharing', 2, 1, 1, 5000.00, 'Available'),
('RM010', 'BLK003', '201', 'Single', 1, 1, 2, 7500.00, 'Full'),
('RM011', 'BLK004', '101', 'Triple Sharing', 3, 3, 1, 4000.00, 'Full'),
('RM012', 'BLK004', '102', 'Double Sharing', 2, 0, 1, 5500.00, 'Available');

-- ========================================
-- HOSTEL ALLOCATIONS
-- ========================================
INSERT INTO hostel_allocations (student_id, room_id, allocation_date, status) VALUES
('STU021', 'RM001', '2025-04-01', 'Active'),
('STU023', 'RM001', '2025-04-01', 'Active'),
('STU025', 'RM002', '2025-04-01', 'Active'),
('STU027', 'RM004', '2025-04-01', 'Active'),
('STU029', 'RM006', '2025-04-01', 'Active'),
('STU041', 'RM006', '2025-04-01', 'Active'),
('STU022', 'RM008', '2025-04-01', 'Active'),
('STU024', 'RM008', '2025-04-01', 'Active'),
('STU026', 'RM009', '2025-04-01', 'Active'),
('STU028', 'RM010', '2025-04-01', 'Active'),
('STU030', 'RM011', '2025-04-01', 'Active'),
('STU042', 'RM011', '2025-04-01', 'Active'),
('STU046', 'RM011', '2025-04-01', 'Active');

-- ========================================
-- EXAMS
-- ========================================
INSERT INTO exams (id, name, class, subject_id, exam_date, start_time, end_time, max_marks, pass_marks, status) VALUES
('EXM001', 'Mid-Term Examination', 'Class 10', 'SUB001', '2025-09-15', '09:00:00', '12:00:00', 100, 33, 'Completed'),
('EXM002', 'Mid-Term Examination', 'Class 10', 'SUB002', '2025-09-16', '09:00:00', '12:00:00', 100, 33, 'Completed'),
('EXM003', 'Mid-Term Examination', 'Class 10', 'SUB003', '2025-09-17', '09:00:00', '12:00:00', 100, 33, 'Completed'),
('EXM004', 'Mid-Term Examination', 'Class 10', 'SUB004', '2025-09-18', '09:00:00', '12:00:00', 100, 33, 'Completed'),
('EXM005', 'Mid-Term Examination', 'Class 10', 'SUB005', '2025-09-19', '09:00:00', '12:00:00', 100, 33, 'Completed'),
('EXM006', 'Final Examination', 'Class 10', 'SUB001', '2025-03-01', '09:00:00', '12:00:00', 100, 33, 'Scheduled'),
('EXM007', 'Final Examination', 'Class 10', 'SUB002', '2025-03-03', '09:00:00', '12:00:00', 100, 33, 'Scheduled'),
('EXM008', 'Final Examination', 'Class 10', 'SUB003', '2025-03-05', '09:00:00', '12:00:00', 100, 33, 'Scheduled'),
('EXM009', 'Mid-Term Examination', 'Class 12', 'SUB010', '2025-09-15', '09:00:00', '12:00:00', 100, 33, 'Completed'),
('EXM010', 'Mid-Term Examination', 'Class 12', 'SUB011', '2025-09-16', '09:00:00', '12:00:00', 100, 33, 'Completed');

-- ========================================
-- EXAM MARKS
-- ========================================
INSERT INTO exam_marks (exam_id, student_id, marks_obtained, entered_by) VALUES
('EXM001', 'STU001', 85.00, 'USR001'),
('EXM001', 'STU002', 78.00, 'USR001'),
('EXM001', 'STU003', 92.00, 'USR001'),
('EXM001', 'STU004', 65.00, 'USR001'),
('EXM001', 'STU005', 88.00, 'USR001'),
('EXM001', 'STU006', 72.00, 'USR001'),
('EXM001', 'STU007', 95.00, 'USR001'),
('EXM001', 'STU008', 68.00, 'USR001'),
('EXM002', 'STU001', 82.00, 'USR001'),
('EXM002', 'STU002', 75.00, 'USR001'),
('EXM002', 'STU003', 88.00, 'USR001'),
('EXM002', 'STU004', 70.00, 'USR001'),
('EXM002', 'STU005', 91.00, 'USR001'),
('EXM003', 'STU001', 79.00, 'USR001'),
('EXM003', 'STU002', 84.00, 'USR001'),
('EXM003', 'STU003', 90.00, 'USR001'),
('EXM009', 'STU021', 87.00, 'USR001'),
('EXM009', 'STU022', 91.00, 'USR001'),
('EXM009', 'STU023', 76.00, 'USR001'),
('EXM010', 'STU021', 83.00, 'USR001'),
('EXM010', 'STU022', 89.00, 'USR001');

-- ========================================
-- NOTIFICATIONS
-- ========================================
INSERT INTO notifications (title, message, type, icon, target_role, is_read) VALUES
('Welcome to New Academic Year', 'Welcome to the academic year 2025-26. Wishing all students a successful year ahead!', 'info', 'fa-graduation-cap', NULL, FALSE),
('Fee Payment Reminder', 'This is a reminder to pay your December fees before 15th December to avoid late fee charges.', 'warning', 'fa-rupee-sign', 'Student', FALSE),
('Mid-Term Results Published', 'Mid-term examination results for all classes have been published. Check your grades now.', 'success', 'fa-chart-line', 'Student', FALSE),
('Parent-Teacher Meeting', 'PTM scheduled for 20th December 2025. All parents are requested to attend.', 'info', 'fa-users', 'Parent', FALSE),
('Holiday Notice', 'School will remain closed on 25th December for Christmas.', 'info', 'fa-calendar', NULL, FALSE);

-- ========================================
-- ACTIVITY LOGS
-- ========================================
INSERT INTO activity_logs (user_id, action, module, details, ip_address) VALUES
('USR001', 'Login', 'Auth', 'Admin logged in successfully', '192.168.1.100'),
('USR001', 'Added Student', 'Students', 'Added new student: Aarav Sharma (STU001)', '192.168.1.100'),
('USR001', 'Added Teacher', 'Teachers', 'Added new teacher: Dr. Rajesh Kumar (TCH001)', '192.168.1.100'),
('USR001', 'Fee Collection', 'Fees', 'Collected fee of Rs. 6000 from STU001', '192.168.1.100'),
('USR001', 'Marked Attendance', 'Attendance', 'Marked attendance for Class 10-A', '192.168.1.100'),
('USR001', 'Book Issued', 'Library', 'Issued book BK001 to STU001', '192.168.1.100'),
('USR001', 'Exam Created', 'Exams', 'Created Mid-Term Examination for Class 10', '192.168.1.100');

-- ========================================
-- UPDATE COUNTERS
-- ========================================

-- Update hostel room occupied counts based on allocations
UPDATE hostel_rooms r
SET occupied = (
    SELECT COUNT(*) FROM hostel_allocations a
    WHERE a.room_id = r.id AND a.status = 'Active'
);

-- Update room status based on occupancy
UPDATE hostel_rooms
SET status = CASE
    WHEN occupied >= capacity THEN 'Full'
    WHEN occupied > 0 THEN 'Available'
    ELSE 'Available'
END;

-- ========================================
-- END OF SAMPLE DATA
-- ========================================
