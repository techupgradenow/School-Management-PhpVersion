/**
 * EduManage Pro - Main Application JavaScript (jQuery)
 * Handles all frontend logic and backend API communication
 */

(function($) {
    'use strict';

    // ========================================
    // CONFIGURATION
    // ========================================

    const API_BASE_URL = '/School-Management-PhpVersion/backend/api';

    const API_ENDPOINTS = {
        auth: `${API_BASE_URL}/auth.php`,
        students: `${API_BASE_URL}/students.php`,
        teachers: `${API_BASE_URL}/teachers.php`,
        attendance: `${API_BASE_URL}/attendance.php`,
        exams: `${API_BASE_URL}/exams.php`,
        fees: `${API_BASE_URL}/fees.php`,
        library: `${API_BASE_URL}/library.php`,
        transport: `${API_BASE_URL}/transport.php`,
        hostel: `${API_BASE_URL}/hostel.php`,
        notifications: `${API_BASE_URL}/notifications.php`
    };

    // ========================================
    // AJAX HELPER FUNCTIONS
    // ========================================

    /**
     * Make API request using jQuery AJAX
     */
    function apiRequest(endpoint, method, data, successCallback, errorCallback) {
        $.ajax({
            url: endpoint,
            type: method,
            dataType: 'json',
            contentType: 'application/json',
            data: method !== 'GET' ? JSON.stringify(data) : data,
            success: function(response) {
                if (response.success) {
                    if (typeof successCallback === 'function') {
                        successCallback(response);
                    }
                } else {
                    if (typeof errorCallback === 'function') {
                        errorCallback(response);
                    } else {
                        showNotification(response.message || 'Operation failed', 'error');
                    }
                }
            },
            error: function(xhr, status, error) {
                const errorMessage = xhr.responseJSON?.message || 'An error occurred. Please try again.';
                if (typeof errorCallback === 'function') {
                    errorCallback({success: false, message: errorMessage, error: error});
                } else {
                    showNotification(errorMessage, 'error');
                }
                console.error('API Error:', error, xhr.responseText);
            }
        });
    }

    /**
     * Show notification to user
     */
    function showNotification(message, type = 'success') {
        const $notification = $('#notification');

        if ($notification.length === 0) {
            // Create notification if it doesn't exist
            $('body').append(`
                <div class="notification ${type}" id="notification">
                    <i class="fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}"></i>
                    <div class="notification-message">${message}</div>
                </div>
            `);
        } else {
            $notification
                .removeClass('success error warning info')
                .addClass(type)
                .find('.notification-message')
                .text(message);

            $notification.find('i')
                .removeClass()
                .addClass(`fas ${type === 'success' ? 'fa-check-circle' : 'fa-exclamation-circle'}`);
        }

        $('#notification').addClass('show');

        setTimeout(function() {
            $('#notification').removeClass('show');
        }, 3000);
    }

    // ========================================
    // AUTHENTICATION MODULE
    // ========================================

    const AuthModule = {
        /**
         * Login user
         */
        login: function(username, password, rememberMe, successCallback, errorCallback) {
            const data = {
                action: 'login',
                username: username,
                password: password,
                remember_me: rememberMe
            };

            apiRequest(API_ENDPOINTS.auth, 'POST', data, successCallback, errorCallback);
        },

        /**
         * Logout user
         */
        logout: function(successCallback) {
            const data = {
                action: 'logout'
            };

            apiRequest(API_ENDPOINTS.auth, 'POST', data, successCallback);
        },

        /**
         * Check authentication status
         */
        checkAuth: function(successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.auth + '?action=check', 'GET', null, successCallback, errorCallback);
        },

        /**
         * Change password
         */
        changePassword: function(currentPassword, newPassword, confirmPassword, successCallback, errorCallback) {
            const data = {
                action: 'change_password',
                current_password: currentPassword,
                new_password: newPassword,
                confirm_password: confirmPassword
            };

            apiRequest(API_ENDPOINTS.auth, 'POST', data, successCallback, errorCallback);
        }
    };

    // ========================================
    // STUDENTS MODULE
    // ========================================

    const StudentsModule = {
        /**
         * Get students list
         */
        getList: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'list'}, params));
            apiRequest(API_ENDPOINTS.students + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get single student
         */
        getSingle: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.students + '?action=single&id=' + id, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get statistics
         */
        getStats: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'stats'}, params));
            apiRequest(API_ENDPOINTS.students + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Create student
         */
        create: function(studentData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.students, 'POST', studentData, successCallback, errorCallback);
        },

        /**
         * Update student
         */
        update: function(studentData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.students, 'PUT', studentData, successCallback, errorCallback);
        },

        /**
         * Delete student
         */
        delete: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.students + '?id=' + id, 'DELETE', null, successCallback, errorCallback);
        },

        /**
         * Bulk delete students
         */
        bulkDelete: function(ids, successCallback, errorCallback) {
            const idsString = Array.isArray(ids) ? ids.join(',') : ids;
            apiRequest(API_ENDPOINTS.students + '?ids=' + idsString, 'DELETE', null, successCallback, errorCallback);
        },

        /**
         * Search students
         */
        search: function(query, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.students + '?action=search&q=' + encodeURIComponent(query), 'GET', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // TEACHERS MODULE
    // ========================================

    const TeachersModule = {
        /**
         * Get teachers list
         */
        getList: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'list'}, params));
            apiRequest(API_ENDPOINTS.teachers + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get single teacher
         */
        getSingle: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.teachers + '?action=single&id=' + id, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get statistics
         */
        getStats: function(successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.teachers + '?action=stats', 'GET', null, successCallback, errorCallback);
        },

        /**
         * Create teacher
         */
        create: function(teacherData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.teachers, 'POST', teacherData, successCallback, errorCallback);
        },

        /**
         * Update teacher
         */
        update: function(teacherData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.teachers, 'PUT', teacherData, successCallback, errorCallback);
        },

        /**
         * Delete teacher
         */
        delete: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.teachers + '?id=' + id, 'DELETE', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // ATTENDANCE MODULE
    // ========================================

    const AttendanceModule = {
        /**
         * Get attendance list
         */
        getList: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'list'}, params));
            apiRequest(API_ENDPOINTS.attendance + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get student attendance history
         */
        getStudentHistory: function(studentId, params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'student', student_id: studentId}, params));
            apiRequest(API_ENDPOINTS.attendance + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get statistics
         */
        getStats: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'stats'}, params));
            apiRequest(API_ENDPOINTS.attendance + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get attendance report
         */
        getReport: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'report'}, params));
            apiRequest(API_ENDPOINTS.attendance + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Mark attendance (bulk)
         */
        mark: function(attendanceData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.attendance, 'POST', attendanceData, successCallback, errorCallback);
        },

        /**
         * Update attendance
         */
        update: function(attendanceData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.attendance, 'PUT', attendanceData, successCallback, errorCallback);
        },

        /**
         * Delete attendance record
         */
        delete: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.attendance + '?id=' + id, 'DELETE', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // EXAMS MODULE
    // ========================================

    const ExamsModule = {
        /**
         * Get exams list
         */
        getList: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'list'}, params));
            apiRequest(API_ENDPOINTS.exams + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get single exam
         */
        getSingle: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?action=single&id=' + id, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get marks for exam
         */
        getMarks: function(examId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?action=marks&exam_id=' + examId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get student marks
         */
        getStudentMarks: function(studentId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?action=student_marks&student_id=' + studentId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get exam statistics
         */
        getStats: function(examId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?action=stats&exam_id=' + examId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get exam results
         */
        getResults: function(examId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?action=results&exam_id=' + examId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Create exam
         */
        create: function(examData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?action=create_exam', 'POST', examData, successCallback, errorCallback);
        },

        /**
         * Enter marks (bulk)
         */
        enterMarks: function(marksData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?action=enter_marks', 'POST', marksData, successCallback, errorCallback);
        },

        /**
         * Update exam
         */
        update: function(examData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams, 'PUT', examData, successCallback, errorCallback);
        },

        /**
         * Delete exam
         */
        delete: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.exams + '?id=' + id, 'DELETE', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // FEES MODULE
    // ========================================

    const FeesModule = {
        /**
         * Get payments list
         */
        getPayments: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'list'}, params));
            apiRequest(API_ENDPOINTS.fees + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get fee structures
         */
        getStructures: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'structures'}, params));
            apiRequest(API_ENDPOINTS.fees + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get student fees
         */
        getStudentFees: function(studentId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=student_fees&student_id=' + studentId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get statistics
         */
        getStats: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'stats'}, params));
            apiRequest(API_ENDPOINTS.fees + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get receipt
         */
        getReceipt: function(receiptNo, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=receipt&receipt_no=' + receiptNo, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get pending fees
         */
        getPending: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'pending'}, params));
            apiRequest(API_ENDPOINTS.fees + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Create fee structure
         */
        createStructure: function(structureData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=create_structure', 'POST', structureData, successCallback, errorCallback);
        },

        /**
         * Record payment
         */
        recordPayment: function(paymentData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=record_payment', 'POST', paymentData, successCallback, errorCallback);
        },

        /**
         * Update fee structure
         */
        updateStructure: function(structureData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=update_structure', 'PUT', structureData, successCallback, errorCallback);
        },

        /**
         * Update payment
         */
        updatePayment: function(paymentData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=update_payment', 'PUT', paymentData, successCallback, errorCallback);
        },

        /**
         * Delete fee structure
         */
        deleteStructure: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=delete_structure&id=' + id, 'DELETE', null, successCallback, errorCallback);
        },

        /**
         * Delete payment
         */
        deletePayment: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.fees + '?action=delete_payment&id=' + id, 'DELETE', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // LIBRARY MODULE
    // ========================================

    const LibraryModule = {
        /**
         * Get books list
         */
        getBooks: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'books'}, params));
            apiRequest(API_ENDPOINTS.library + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get single book
         */
        getSingleBook: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?action=single_book&id=' + id, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get issues list
         */
        getIssues: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'issues'}, params));
            apiRequest(API_ENDPOINTS.library + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get student issues
         */
        getStudentIssues: function(studentId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?action=student_issues&student_id=' + studentId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get statistics
         */
        getStats: function(successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?action=stats', 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get overdue books
         */
        getOverdue: function(successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?action=overdue', 'GET', null, successCallback, errorCallback);
        },

        /**
         * Add book
         */
        addBook: function(bookData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?action=add_book', 'POST', bookData, successCallback, errorCallback);
        },

        /**
         * Issue book
         */
        issueBook: function(issueData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?action=issue_book', 'POST', issueData, successCallback, errorCallback);
        },

        /**
         * Return book
         */
        returnBook: function(returnData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?action=return_book', 'POST', returnData, successCallback, errorCallback);
        },

        /**
         * Update book
         */
        updateBook: function(bookData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library, 'PUT', bookData, successCallback, errorCallback);
        },

        /**
         * Delete book
         */
        deleteBook: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.library + '?id=' + id, 'DELETE', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // TRANSPORT MODULE
    // ========================================

    const TransportModule = {
        /**
         * Get routes list
         */
        getRoutes: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'routes'}, params));
            apiRequest(API_ENDPOINTS.transport + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get single route with stops
         */
        getSingleRoute: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=single_route&id=' + id, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get route stops
         */
        getStops: function(routeId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=stops&route_id=' + routeId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get assignments
         */
        getAssignments: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'assignments'}, params));
            apiRequest(API_ENDPOINTS.transport + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get student assignment
         */
        getStudentAssignment: function(studentId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=student_assignment&student_id=' + studentId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get statistics
         */
        getStats: function(successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=stats', 'GET', null, successCallback, errorCallback);
        },

        /**
         * Create route
         */
        createRoute: function(routeData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=create_route', 'POST', routeData, successCallback, errorCallback);
        },

        /**
         * Add stop
         */
        addStop: function(stopData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=add_stop', 'POST', stopData, successCallback, errorCallback);
        },

        /**
         * Assign student
         */
        assignStudent: function(assignmentData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=assign_student', 'POST', assignmentData, successCallback, errorCallback);
        },

        /**
         * Update route
         */
        updateRoute: function(routeData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=update_route', 'PUT', routeData, successCallback, errorCallback);
        },

        /**
         * Update stop
         */
        updateStop: function(stopData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=update_stop', 'PUT', stopData, successCallback, errorCallback);
        },

        /**
         * Update assignment
         */
        updateAssignment: function(assignmentData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=update_assignment', 'PUT', assignmentData, successCallback, errorCallback);
        },

        /**
         * Delete route
         */
        deleteRoute: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=delete_route&id=' + id, 'DELETE', null, successCallback, errorCallback);
        },

        /**
         * Delete stop
         */
        deleteStop: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=delete_stop&id=' + id, 'DELETE', null, successCallback, errorCallback);
        },

        /**
         * Delete assignment
         */
        deleteAssignment: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.transport + '?action=delete_assignment&id=' + id, 'DELETE', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // HOSTEL MODULE
    // ========================================

    const HostelModule = {
        /**
         * Get hostel blocks
         */
        getBlocks: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'blocks'}, params));
            apiRequest(API_ENDPOINTS.hostel + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get single block
         */
        getSingleBlock: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=single_block&id=' + id, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get rooms
         */
        getRooms: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'rooms'}, params));
            apiRequest(API_ENDPOINTS.hostel + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get single room with occupants
         */
        getSingleRoom: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=single_room&id=' + id, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get allocations
         */
        getAllocations: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'allocations'}, params));
            apiRequest(API_ENDPOINTS.hostel + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get student allocation
         */
        getStudentAllocation: function(studentId, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=student_allocation&student_id=' + studentId, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get statistics
         */
        getStats: function(successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=stats', 'GET', null, successCallback, errorCallback);
        },

        /**
         * Get available rooms
         */
        getAvailableRooms: function(params, successCallback, errorCallback) {
            const queryString = $.param(Object.assign({action: 'available_rooms'}, params));
            apiRequest(API_ENDPOINTS.hostel + '?' + queryString, 'GET', null, successCallback, errorCallback);
        },

        /**
         * Create hostel block
         */
        createBlock: function(blockData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=create_block', 'POST', blockData, successCallback, errorCallback);
        },

        /**
         * Create room
         */
        createRoom: function(roomData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=create_room', 'POST', roomData, successCallback, errorCallback);
        },

        /**
         * Allocate room
         */
        allocateRoom: function(allocationData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=allocate_room', 'POST', allocationData, successCallback, errorCallback);
        },

        /**
         * Checkout student
         */
        checkout: function(checkoutData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=checkout', 'POST', checkoutData, successCallback, errorCallback);
        },

        /**
         * Update hostel block
         */
        updateBlock: function(blockData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=update_block', 'PUT', blockData, successCallback, errorCallback);
        },

        /**
         * Update room
         */
        updateRoom: function(roomData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=update_room', 'PUT', roomData, successCallback, errorCallback);
        },

        /**
         * Update allocation
         */
        updateAllocation: function(allocationData, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=update_allocation', 'PUT', allocationData, successCallback, errorCallback);
        },

        /**
         * Delete hostel block
         */
        deleteBlock: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=delete_block&id=' + id, 'DELETE', null, successCallback, errorCallback);
        },

        /**
         * Delete room
         */
        deleteRoom: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=delete_room&id=' + id, 'DELETE', null, successCallback, errorCallback);
        },

        /**
         * Delete allocation
         */
        deleteAllocation: function(id, successCallback, errorCallback) {
            apiRequest(API_ENDPOINTS.hostel + '?action=delete_allocation&id=' + id, 'DELETE', null, successCallback, errorCallback);
        }
    };

    // ========================================
    // EXPOSE MODULES TO WINDOW
    // ========================================

    window.EduManageApp = {
        Auth: AuthModule,
        Students: StudentsModule,
        Teachers: TeachersModule,
        Attendance: AttendanceModule,
        Exams: ExamsModule,
        Fees: FeesModule,
        Library: LibraryModule,
        Transport: TransportModule,
        Hostel: HostelModule,
        showNotification: showNotification,
        apiRequest: apiRequest,
        API_ENDPOINTS: API_ENDPOINTS
    };

    // ========================================
    // DOM READY
    // ========================================

    $(document).ready(function() {
        console.log('EduManage Pro - Application Initialized');

        // Add any global jQuery event handlers here
        // For example: global AJAX error handlers, loading indicators, etc.

        // Global AJAX loading indicator
        $(document).ajaxStart(function() {
            // Show loading indicator
            $('body').addClass('loading');
        }).ajaxStop(function() {
            // Hide loading indicator
            $('body').removeClass('loading');
        });
    });

})(jQuery);
