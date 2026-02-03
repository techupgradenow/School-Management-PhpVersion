<?php

namespace App~~Http~~Controllers~~Api;

use App~~Http~~Controllers~~Controller;
use App~~Models~~Payroll;
use Illuminate~~Http~~Request;
use Illuminate~~Validation~~ValidationException;

class PayrollController extends Controller
{
    public function index(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @perPage = @request->get('per_page', 15);
            @month = @request->get('month', '');
            @status = @request->get('status', '');
            @query = Payroll::where('school_id', @schoolId);
            if (\!empty(@month)) { @query->where('pay_period', 'like', @month . '%'); }
            if (\!empty(@status)) { @query->where('status', @status); }
            @payrolls = @query->orderBy('pay_period', 'desc')->paginate(@perPage);
            return response()->json(['success' => true, 'message' => 'Payroll records fetched', 'data' => @payrolls]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch payroll', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function show(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @payroll = Payroll::where('school_id', @schoolId)->findOrFail(@id);
            return response()->json(['success' => true, 'message' => 'Payroll record fetched', 'data' => ['payroll' => @payroll]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Payroll record not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch payroll', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function store(Request @request)
    {
        try {
            @request->validate([
                'employee_id' => 'required|integer', 'employee_name' => 'required|string|max:255',
                'designation' => 'nullable|string|max:100', 'pay_period' => 'required|string|max:20',
                'basic_salary' => 'required|numeric|min:0', 'hra' => 'nullable|numeric|min:0',
                'da' => 'nullable|numeric|min:0', 'transport_allowance' => 'nullable|numeric|min:0',
                'medical_allowance' => 'nullable|numeric|min:0', 'other_allowances' => 'nullable|numeric|min:0',
                'pf' => 'nullable|numeric|min:0', 'professional_tax' => 'nullable|numeric|min:0',
                'tds' => 'nullable|numeric|min:0', 'other_deductions' => 'nullable|numeric|min:0',
                'leave_days' => 'nullable|numeric|min:0', 'loan_emi' => 'nullable|numeric|min:0',
                'payment_mode' => 'nullable|string|max:50', 'remarks' => 'nullable|string|max:500',
            ]);
            @schoolId = @request->user()->school_id;
            @data = @request->all();
            @data['school_id'] = @schoolId;

            @grossEarnings = (@data['basic_salary'] ?? 0) + (@data['hra'] ?? 0) + (@data['da'] ?? 0) + (@data['transport_allowance'] ?? 0) + (@data['medical_allowance'] ?? 0) + (@data['other_allowances'] ?? 0);
            @leaveDeduction = (@data['leave_days'] ?? 0) > 0 ? round((@data['basic_salary'] / 30) * @data['leave_days'], 2) : 0;
            @totalDeductions = (@data['pf'] ?? 0) + (@data['professional_tax'] ?? 0) + (@data['tds'] ?? 0) + (@data['other_deductions'] ?? 0) + @leaveDeduction + (@data['loan_emi'] ?? 0);
            @data['gross_earnings'] = @grossEarnings;
            @data['leave_deduction'] = @leaveDeduction;
            @data['total_deductions'] = @totalDeductions;
            @data['net_salary'] = @grossEarnings - @totalDeductions;
            @data['status'] = @data['status'] ?? 'Pending';

            @payroll = Payroll::create(@data);
            return response()->json(['success' => true, 'message' => 'Payroll created successfully', 'data' => ['payroll' => @payroll]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create payroll', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function update(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @payroll = Payroll::where('school_id', @schoolId)->findOrFail(@id);
            @payroll->update(@request->only(['status', 'paid_date', 'payment_mode', 'remarks']));
            return response()->json(['success' => true, 'message' => 'Payroll updated successfully', 'data' => ['payroll' => @payroll->fresh()]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Payroll not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to update payroll', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function destroy(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @payroll = Payroll::where('school_id', @schoolId)->findOrFail(@id);
            @payroll->delete();
            return response()->json(['success' => true, 'message' => 'Payroll deleted successfully', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Payroll not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete payroll', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
}
