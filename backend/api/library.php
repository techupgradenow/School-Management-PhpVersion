<?php
/**
 * Library API Endpoint
 * EduManage Pro - School Management System
 *
 * Handles all CRUD operations for library books and issues
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../helpers/functions.php';

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Get request data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

// Get database connection
try {
    $db = getDB();
} catch (Exception $e) {
    sendResponse(false, 'Database connection failed', null, ['database' => $e->getMessage()]);
}

// Route requests
switch ($method) {
    case 'GET':
        handleGet($db, $_GET);
        break;

    case 'POST':
        handlePost($db, $data);
        break;

    case 'PUT':
        handlePut($db, $data);
        break;

    case 'DELETE':
        handleDelete($db, $_GET);
        break;

    default:
        sendResponse(false, 'Method not allowed', null, ['method' => 'Unsupported HTTP method']);
}

/**
 * Handle GET requests
 */
function handleGet($db, $params) {
    try {
        $action = $params['action'] ?? 'books';

        switch ($action) {
            case 'books':
                getBooksList($db, $params);
                break;

            case 'single_book':
                getSingleBook($db, $params);
                break;

            case 'issues':
                getIssuesList($db, $params);
                break;

            case 'student_issues':
                getStudentIssues($db, $params);
                break;

            case 'stats':
                getLibraryStats($db, $params);
                break;

            case 'overdue':
                getOverdueBooks($db, $params);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Get books list
 */
function getBooksList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $category = $params['category'] ?? '';
    $search = $params['search'] ?? '';
    $availableOnly = isset($params['available_only']) ? (bool)$params['available_only'] : false;

    $where = [];
    $bindings = [];

    if (!empty($category)) {
        $where[] = "category = :category";
        $bindings[':category'] = $category;
    }

    if (!empty($search)) {
        $where[] = "(title LIKE :search OR author LIKE :search OR isbn LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    if ($availableOnly) {
        $where[] = "available > 0";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "SELECT COUNT(*) as total FROM library_books $whereClause";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "
        SELECT * FROM library_books
        $whereClause
        ORDER BY title ASC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $books = $stmt->fetchAll();

    $response = [
        'books' => $books,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Books fetched successfully', $response);
}

/**
 * Get single book
 */
function getSingleBook($db, $params) {
    if (empty($params['id'])) {
        sendResponse(false, 'Book ID is required');
    }

    $stmt = $db->prepare("SELECT * FROM library_books WHERE id = :id");
    $stmt->execute([':id' => $params['id']]);
    $book = $stmt->fetch();

    if (!$book) {
        sendResponse(false, 'Book not found');
    }

    sendResponse(true, 'Book fetched successfully', $book);
}

/**
 * Get issues list
 */
function getIssuesList($db, $params) {
    $page = isset($params['page']) ? (int)$params['page'] : 1;
    $perPage = isset($params['perPage']) ? (int)$params['perPage'] : 10;
    $status = $params['status'] ?? '';
    $search = $params['search'] ?? '';

    $where = [];
    $bindings = [];

    if (!empty($status)) {
        $where[] = "library_issues.status = :status";
        $bindings[':status'] = $status;
    }

    if (!empty($search)) {
        $where[] = "(students.name LIKE :search OR library_books.title LIKE :search)";
        $bindings[':search'] = "%$search%";
    }

    $whereClause = !empty($where) ? 'WHERE ' . implode(' AND ', $where) : '';

    // Get total count
    $countQuery = "
        SELECT COUNT(*) as total
        FROM library_issues
        LEFT JOIN students ON library_issues.student_id = students.id
        LEFT JOIN library_books ON library_issues.book_id = library_books.id
        $whereClause
    ";
    $countStmt = $db->prepare($countQuery);
    $countStmt->execute($bindings);
    $totalRecords = $countStmt->fetch()['total'];

    // Get paginated records
    $offset = ($page - 1) * $perPage;
    $query = "
        SELECT
            library_issues.*,
            students.name as student_name,
            students.class,
            students.section,
            students.roll_no,
            library_books.title as book_title,
            library_books.author,
            library_books.isbn
        FROM library_issues
        LEFT JOIN students ON library_issues.student_id = students.id
        LEFT JOIN library_books ON library_issues.book_id = library_books.id
        $whereClause
        ORDER BY library_issues.issue_date DESC
        LIMIT :limit OFFSET :offset
    ";

    $stmt = $db->prepare($query);
    foreach ($bindings as $key => $value) {
        $stmt->bindValue($key, $value);
    }
    $stmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);

    $stmt->execute();
    $issues = $stmt->fetchAll();

    $response = [
        'issues' => $issues,
        'pagination' => [
            'page' => $page,
            'perPage' => $perPage,
            'total' => $totalRecords,
            'totalPages' => ceil($totalRecords / $perPage)
        ]
    ];

    sendResponse(true, 'Issues fetched successfully', $response);
}

/**
 * Get student issues history
 */
function getStudentIssues($db, $params) {
    if (empty($params['student_id'])) {
        sendResponse(false, 'Student ID is required');
    }

    $studentId = $params['student_id'];

    $query = "
        SELECT
            library_issues.*,
            library_books.title as book_title,
            library_books.author,
            library_books.isbn
        FROM library_issues
        LEFT JOIN library_books ON library_issues.book_id = library_books.id
        WHERE library_issues.student_id = :student_id
        ORDER BY library_issues.issue_date DESC
    ";

    $stmt = $db->prepare($query);
    $stmt->execute([':student_id' => $studentId]);
    $issues = $stmt->fetchAll();

    sendResponse(true, 'Student issues fetched successfully', ['issues' => $issues]);
}

/**
 * Get library statistics
 */
function getLibraryStats($db, $params) {
    // Total books
    $totalBooksStmt = $db->query("SELECT SUM(quantity) as total FROM library_books");
    $totalBooks = $totalBooksStmt->fetch()['total'] ?? 0;

    // Available books
    $availableBooksStmt = $db->query("SELECT SUM(available) as total FROM library_books");
    $availableBooks = $availableBooksStmt->fetch()['total'] ?? 0;

    // Issued books
    $issuedBooks = $totalBooks - $availableBooks;

    // Total issues (currently issued)
    $currentIssuesStmt = $db->query("SELECT COUNT(*) as total FROM library_issues WHERE status = 'Issued'");
    $currentIssues = $currentIssuesStmt->fetch()['total'];

    // Overdue books
    $overdueStmt = $db->query("
        SELECT COUNT(*) as total
        FROM library_issues
        WHERE status = 'Issued' AND due_date < CURDATE()
    ");
    $overdue = $overdueStmt->fetch()['total'];

    // Categories count
    $categoriesStmt = $db->query("SELECT COUNT(DISTINCT category) as total FROM library_books");
    $categories = $categoriesStmt->fetch()['total'];

    $stats = [
        'total_books' => $totalBooks,
        'available_books' => $availableBooks,
        'issued_books' => $issuedBooks,
        'current_issues' => $currentIssues,
        'overdue_books' => $overdue,
        'categories' => $categories
    ];

    sendResponse(true, 'Statistics fetched successfully', $stats);
}

/**
 * Get overdue books
 */
function getOverdueBooks($db, $params) {
    $query = "
        SELECT
            library_issues.*,
            students.name as student_name,
            students.class,
            students.section,
            students.contact,
            library_books.title as book_title,
            library_books.author,
            DATEDIFF(CURDATE(), library_issues.due_date) as days_overdue
        FROM library_issues
        LEFT JOIN students ON library_issues.student_id = students.id
        LEFT JOIN library_books ON library_issues.book_id = library_books.id
        WHERE library_issues.status = 'Issued' AND library_issues.due_date < CURDATE()
        ORDER BY library_issues.due_date ASC
    ";

    $stmt = $db->query($query);
    $overdueBooks = $stmt->fetchAll();

    sendResponse(true, 'Overdue books fetched successfully', ['overdue_books' => $overdueBooks]);
}

/**
 * Handle POST - Add book or issue book
 */
function handlePost($db, $data) {
    try {
        $action = $data['action'] ?? 'add_book';

        switch ($action) {
            case 'add_book':
                addBook($db, $data);
                break;

            case 'issue_book':
                issueBook($db, $data);
                break;

            case 'return_book':
                returnBook($db, $data);
                break;

            default:
                sendResponse(false, 'Invalid action');
        }
    } catch (Exception $e) {
        sendResponse(false, 'Error processing request', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Add book
 */
function addBook($db, $data) {
    // Validate required fields
    $required = ['title', 'author', 'category', 'quantity'];
    $errors = validateRequired($data, $required);

    if (!empty($errors)) {
        sendResponse(false, 'Validation failed', null, $errors);
    }

    // Sanitize input
    $title = sanitizeInput($data['title']);
    $author = sanitizeInput($data['author']);
    $isbn = isset($data['isbn']) ? sanitizeInput($data['isbn']) : null;
    $category = sanitizeInput($data['category']);
    $quantity = (int)$data['quantity'];
    $available = $quantity; // Initially all books are available
    $publisher = isset($data['publisher']) ? sanitizeInput($data['publisher']) : null;
    $publicationYear = isset($data['publication_year']) ? (int)$data['publication_year'] : null;
    $language = isset($data['language']) ? sanitizeInput($data['language']) : 'English';
    $shelfNo = isset($data['shelf_no']) ? sanitizeInput($data['shelf_no']) : null;
    $price = isset($data['price']) ? (float)$data['price'] : null;

    // Generate book ID
    $bookId = generateId('BK', 10);

    // Insert book
    $stmt = $db->prepare("
        INSERT INTO library_books (id, title, author, isbn, category, quantity, available, publisher, publication_year, language, shelf_no, price)
        VALUES (:id, :title, :author, :isbn, :category, :quantity, :available, :publisher, :publication_year, :language, :shelf_no, :price)
    ");

    $stmt->execute([
        ':id' => $bookId,
        ':title' => $title,
        ':author' => $author,
        ':isbn' => $isbn,
        ':category' => $category,
        ':quantity' => $quantity,
        ':available' => $available,
        ':publisher' => $publisher,
        ':publication_year' => $publicationYear,
        ':language' => $language,
        ':shelf_no' => $shelfNo,
        ':price' => $price
    ]);

    // Log activity
    logActivity($db, getCurrentUserId(), 'Added library book', 'Library', [
        'book_id' => $bookId,
        'title' => $title
    ]);

    sendResponse(true, 'Book added successfully', ['id' => $bookId]);
}

/**
 * Issue book
 */
function issueBook($db, $data) {
    try {
        // Validate required fields
        $required = ['book_id', 'student_id', 'issue_date', 'due_date'];
        $errors = validateRequired($data, $required);

        if (!empty($errors)) {
            sendResponse(false, 'Validation failed', null, $errors);
        }

        // Sanitize input
        $bookId = sanitizeInput($data['book_id']);
        $studentId = sanitizeInput($data['student_id']);
        $issueDate = formatDateForDB($data['issue_date']);
        $dueDate = formatDateForDB($data['due_date']);
        $remarks = isset($data['remarks']) ? sanitizeInput($data['remarks']) : null;
        $issuedBy = getCurrentUserId();

        // Check if book is available
        $bookStmt = $db->prepare("SELECT available FROM library_books WHERE id = :id");
        $bookStmt->execute([':id' => $bookId]);
        $book = $bookStmt->fetch();

        if (!$book) {
            sendResponse(false, 'Book not found');
        }

        if ($book['available'] <= 0) {
            sendResponse(false, 'Book is not available for issue');
        }

        $db->beginTransaction();

        // Insert issue record
        $stmt = $db->prepare("
            INSERT INTO library_issues (book_id, student_id, issue_date, due_date, status, remarks, issued_by)
            VALUES (:book_id, :student_id, :issue_date, :due_date, 'Issued', :remarks, :issued_by)
        ");

        $stmt->execute([
            ':book_id' => $bookId,
            ':student_id' => $studentId,
            ':issue_date' => $issueDate,
            ':due_date' => $dueDate,
            ':remarks' => $remarks,
            ':issued_by' => $issuedBy
        ]);

        $issueId = $db->lastInsertId();

        // Update book availability
        $updateStmt = $db->prepare("UPDATE library_books SET available = available - 1 WHERE id = :id");
        $updateStmt->execute([':id' => $bookId]);

        $db->commit();

        // Log activity
        logActivity($db, $issuedBy, 'Issued library book', 'Library', [
            'issue_id' => $issueId,
            'book_id' => $bookId,
            'student_id' => $studentId
        ]);

        sendResponse(true, 'Book issued successfully', ['id' => $issueId]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error issuing book', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Return book
 */
function returnBook($db, $data) {
    try {
        // Validate required fields
        if (empty($data['issue_id'])) {
            sendResponse(false, 'Issue ID is required');
        }

        $issueId = (int)$data['issue_id'];
        $returnDate = isset($data['return_date']) ? formatDateForDB($data['return_date']) : date('Y-m-d');
        $fineAmount = isset($data['fine_amount']) ? (float)$data['fine_amount'] : 0;
        $remarks = isset($data['remarks']) ? sanitizeInput($data['remarks']) : null;

        // Get issue details
        $issueStmt = $db->prepare("SELECT book_id, status FROM library_issues WHERE id = :id");
        $issueStmt->execute([':id' => $issueId]);
        $issue = $issueStmt->fetch();

        if (!$issue) {
            sendResponse(false, 'Issue record not found');
        }

        if ($issue['status'] === 'Returned') {
            sendResponse(false, 'Book already returned');
        }

        $db->beginTransaction();

        // Update issue record
        $updateIssueStmt = $db->prepare("
            UPDATE library_issues
            SET return_date = :return_date, status = 'Returned', fine_amount = :fine_amount, remarks = :remarks
            WHERE id = :id
        ");

        $updateIssueStmt->execute([
            ':return_date' => $returnDate,
            ':fine_amount' => $fineAmount,
            ':remarks' => $remarks,
            ':id' => $issueId
        ]);

        // Update book availability
        $updateBookStmt = $db->prepare("UPDATE library_books SET available = available + 1 WHERE id = :id");
        $updateBookStmt->execute([':id' => $issue['book_id']]);

        $db->commit();

        // Log activity
        logActivity($db, getCurrentUserId(), 'Book returned', 'Library', [
            'issue_id' => $issueId,
            'book_id' => $issue['book_id']
        ]);

        sendResponse(true, 'Book returned successfully', ['id' => $issueId]);

    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        sendResponse(false, 'Error returning book', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle PUT - Update book
 */
function handlePut($db, $data) {
    try {
        if (empty($data['id'])) {
            sendResponse(false, 'Book ID is required');
        }

        $bookId = sanitizeInput($data['id']);

        // Check if book exists
        $checkStmt = $db->prepare("SELECT id FROM library_books WHERE id = :id");
        $checkStmt->execute([':id' => $bookId]);

        if (!$checkStmt->fetch()) {
            sendResponse(false, 'Book not found');
        }

        // Build update query
        $fields = [];
        $bindings = [':id' => $bookId];

        $updateableFields = ['title', 'author', 'isbn', 'category', 'quantity', 'publisher', 'publication_year', 'language', 'shelf_no', 'price'];

        foreach ($updateableFields as $field) {
            if (isset($data[$field])) {
                if (in_array($field, ['quantity', 'publication_year'])) {
                    $fields[] = "$field = :$field";
                    $bindings[":$field"] = (int)$data[$field];
                } elseif ($field === 'price') {
                    $fields[] = "$field = :$field";
                    $bindings[":$field"] = (float)$data[$field];
                } else {
                    $fields[] = "$field = :$field";
                    $bindings[":$field"] = sanitizeInput($data[$field]);
                }
            }
        }

        if (empty($fields)) {
            sendResponse(false, 'No fields to update');
        }

        // Update book
        $query = "UPDATE library_books SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $db->prepare($query);
        $stmt->execute($bindings);

        // Log activity
        logActivity($db, getCurrentUserId(), 'Updated library book', 'Library', ['book_id' => $bookId]);

        sendResponse(true, 'Book updated successfully', ['id' => $bookId]);

    } catch (Exception $e) {
        sendResponse(false, 'Error updating book', null, ['error' => $e->getMessage()]);
    }
}

/**
 * Handle DELETE - Delete book
 */
function handleDelete($db, $params) {
    try {
        if (empty($params['id'])) {
            sendResponse(false, 'Book ID is required');
        }

        $bookId = sanitizeInput($params['id']);

        // Check if book has active issues
        $issuesStmt = $db->prepare("
            SELECT COUNT(*) as total FROM library_issues
            WHERE book_id = :book_id AND status = 'Issued'
        ");
        $issuesStmt->execute([':book_id' => $bookId]);
        $activeIssues = $issuesStmt->fetch()['total'];

        if ($activeIssues > 0) {
            sendResponse(false, 'Cannot delete book with active issues');
        }

        $stmt = $db->prepare("DELETE FROM library_books WHERE id = :id");
        $stmt->execute([':id' => $bookId]);

        if ($stmt->rowCount() === 0) {
            sendResponse(false, 'Book not found');
        }

        // Log activity
        logActivity($db, getCurrentUserId(), 'Deleted library book', 'Library', ['book_id' => $bookId]);

        sendResponse(true, 'Book deleted successfully', ['id' => $bookId]);

    } catch (Exception $e) {
        sendResponse(false, 'Error deleting book', null, ['error' => $e->getMessage()]);
    }
}
?>
