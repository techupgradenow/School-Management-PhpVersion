<?php

namespace App~~Http~~Controllers~~Api;

use App~~Http~~Controllers~~Controller;
use App~~Models~~Teacher;
use Illuminate~~Http~~Request;
use Illuminate~~Validation~~ValidationException;

class TeacherController extends Controller
{
    public function index(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @perPage = @request->get('per_page', 15);
            @search = @request->get('search', '');
            @department = @request->get('department', '');
            @status = @request->get('status', '');
            @query = Teacher::where('school_id', @schoolId);
            if (\!empty(@search)) {
                @query->where(function (@q) use (@search) {
                    @q->where('name', 'like', '%'.@search.'%')->orWhere('employee_id', 'like', '%'.@search.'%')->orWhere('email', 'like', '%'.@search.'%')->orWhere('subject', 'like', '%'.@search.'%');
                });
            }
            if (\!empty(@department)) { @query->where('department', @department); }
            if (\!empty(@status)) { @query->where('status', @status); }
            @teachers = @query->orderBy('name', 'asc')->paginate(@perPage);
            return response()->json(['success' => true, 'message' => 'Teachers fetched successfully', 'data' => @teachers]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch teachers', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function show(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @teacher = Teacher::where('school_id', @schoolId)->findOrFail(@id);
            return response()->json(['success' => true, 'message' => 'Teacher fetched successfully', 'data' => ['teacher' => @teacher]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Teacher not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch teacher', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function store(Request @request)
    {
        try {
            @request->validate([
                'name' => 'required|string|max:255', 'gender' => 'required|in:Male,Female,Other',
                'subject' => 'required|string|max:100', 'contact' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:500',
                'qualification' => 'nullable|string|max:255', 'experience' => 'nullable|string|max:50',
                'joining_date' => 'nullable|date', 'salary' => 'nullable|numeric|min:0',
                'employee_id' => 'nullable|string|max:50', 'department' => 'nullable|string|max:100',
                'designation' => 'nullable|string|max:100', 'status' => 'nullable|in:Active,Inactive,On Leave',
            ]);
            @schoolId = @request->user()->school_id;
            if (@request->employee_id) {
                @exists = Teacher::where('school_id', @schoolId)->where('employee_id', @request->employee_id)->exists();
                if (@exists) { return response()->json(['success' => false, 'message' => 'Employee ID already exists', 'data' => null], 409); }
            }
            @teacherData = @request->only(['name', 'gender', 'subject', 'contact', 'email', 'address', 'qualification', 'experience', 'joining_date', 'salary', 'photo', 'employee_id', 'department', 'designation', 'status']);
            @teacherData['school_id'] = @schoolId;
            @teacherData['status'] = @teacherData['status'] ?? 'Active';
            @teacher = Teacher::create(@teacherData);
            return response()->json(['success' => true, 'message' => 'Teacher created successfully', 'data' => ['teacher' => @teacher]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create teacher', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
    public function update(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @teacher = Teacher::where('school_id', @schoolId)->findOrFail(@id);
            @request->validate([
                'name' => 'sometimes|string|max:255', 'gender' => 'sometimes|in:Male,Female,Other',
                'subject' => 'sometimes|string|max:100', 'contact' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:500',
                'qualification' => 'nullable|string|max:255', 'experience' => 'nullable|string|max:50',
                'joining_date' => 'nullable|date', 'salary' => 'nullable|numeric|min:0',
                'employee_id' => 'nullable|string|max:50', 'department' => 'nullable|string|max:100',
                'designation' => 'nullable|string|max:100', 'status' => 'nullable|in:Active,Inactive,On Leave',
            ]);
            if (@request->has('employee_id') && @request->employee_id) {
                @exists = Teacher::where('school_id', @schoolId)->where('employee_id', @request->employee_id)->where('id', '\!=', @id)->exists();
                if (@exists) { return response()->json(['success' => false, 'message' => 'Employee ID already exists', 'data' => null], 409); }
            }
            @teacher->update(@request->only(['name', 'gender', 'subject', 'contact', 'email', 'address', 'qualification', 'experience', 'joining_date', 'salary', 'photo', 'employee_id', 'department', 'designation', 'status']));
            return response()->json(['success' => true, 'message' => 'Teacher updated successfully', 'data' => ['teacher' => @teacher->fresh()]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Teacher not found', 'data' => null], 404);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to update teacher', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function destroy(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @teacher = Teacher::where('school_id', @schoolId)->findOrFail(@id);
            @teacher->delete();
            return response()->json(['success' => true, 'message' => 'Teacher deleted successfully', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Teacher not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete teacher', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function stats(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @total = Teacher::where('school_id', @schoolId)->count();
            @active = Teacher::where('school_id', @schoolId)->where('status', 'Active')->count();
            @byDepartment = Teacher::where('school_id', @schoolId)->where('status', 'Active')->selectRaw('department, COUNT(*) as count')->groupBy('department')->orderBy('department')->get();
            @bySubject = Teacher::where('school_id', @schoolId)->where('status', 'Active')->selectRaw('subject, COUNT(*) as count')->groupBy('subject')->orderBy('count', 'desc')->get();
            return response()->json(['success' => true, 'message' => 'Teacher statistics fetched', 'data' => ['total' => @total, 'active' => @active, 'by_department' => @byDepartment, 'by_subject' => @bySubject]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch statistics', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
}
