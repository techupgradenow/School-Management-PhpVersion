<?php

namespace App~~Http~~Controllers~~Api;

use App~~Http~~Controllers~~Controller;
use App~~Models~~FeeStructure;
use App~~Models~~FeePayment;
use Illuminate~~Http~~Request;
use Illuminate~~Validation~~ValidationException;
use Illuminate~~Support~~Facades~~DB;

class FeeController extends Controller
{
    public function index(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @perPage = @request->get('per_page', 15);
            @class = @request->get('class', '');
            @type = @request->get('type', '');
            @query = FeeStructure::where('school_id', @schoolId);
            if (\!empty(@class)) { @query->where('class', @class); }
            if (\!empty(@type)) { @query->where('fee_type', @type); }
            @fees = @query->orderBy('class')->orderBy('fee_type')->paginate(@perPage);
            return response()->json(['success' => true, 'message' => 'Fee structures fetched', 'data' => @fees]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch fees', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function show(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @fee = FeeStructure::where('school_id', @schoolId)->with('payments')->findOrFail(@id);
            return response()->json(['success' => true, 'message' => 'Fee structure fetched', 'data' => ['fee' => @fee]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Fee structure not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch fee', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function store(Request @request)
    {
        try {
            @request->validate([
                'class' => 'required|string|max:50', 'fee_type' => 'required|string|max:100',
                'amount' => 'required|numeric|min:0', 'frequency' => 'nullable|string|max:50',
                'description' => 'nullable|string|max:255', 'is_active' => 'nullable|boolean',
            ]);
            @schoolId = @request->user()->school_id;
            @feeData = @request->only(['class', 'fee_type', 'amount', 'frequency', 'description', 'is_active']);
            @feeData['school_id'] = @schoolId;
            @feeData['is_active'] = @feeData['is_active'] ?? true;
            @fee = FeeStructure::create(@feeData);
            return response()->json(['success' => true, 'message' => 'Fee structure created', 'data' => ['fee' => @fee]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create fee', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function update(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @fee = FeeStructure::where('school_id', @schoolId)->findOrFail(@id);
            @fee->update(@request->only(['class', 'fee_type', 'amount', 'frequency', 'description', 'is_active']));
            return response()->json(['success' => true, 'message' => 'Fee structure updated', 'data' => ['fee' => @fee->fresh()]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Fee structure not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to update fee', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function destroy(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @fee = FeeStructure::where('school_id', @schoolId)->findOrFail(@id);
            @fee->delete();
            return response()->json(['success' => true, 'message' => 'Fee structure deleted', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Fee structure not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete fee', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function summary(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @totalCollected = FeePayment::where('school_id', @schoolId)->where('status', 'Paid')->sum('amount_paid');
            @totalPending = FeePayment::where('school_id', @schoolId)->where('status', 'Pending')->sum('amount_paid');
            @byType = FeePayment::where('fee_payments.school_id', @schoolId)->where('fee_payments.status', 'Paid')
                ->join('fee_structures', 'fee_payments.fee_structure_id', '=', 'fee_structures.id')
                ->selectRaw('fee_structures.fee_type, SUM(fee_payments.amount_paid) as total')
                ->groupBy('fee_structures.fee_type')->get();
            return response()->json(['success' => true, 'message' => 'Fee summary fetched', 'data' => ['total_collected' => @totalCollected, 'total_pending' => @totalPending, 'by_type' => @byType]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch summary', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function outstanding(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @class = @request->get('class', '');
            @query = FeePayment::where('fee_payments.school_id', @schoolId)->where('fee_payments.status', 'Pending')
                ->join('students', 'fee_payments.student_id', '=', 'students.id')
                ->select('fee_payments.*', 'students.name as student_name', 'students.class', 'students.section');
            if (\!empty(@class)) { @query->where('students.class', @class); }
            @outstanding = @query->orderBy('fee_payments.created_at', 'desc')->paginate(15);
            return response()->json(['success' => true, 'message' => 'Outstanding fees fetched', 'data' => @outstanding]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch outstanding fees', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
}
