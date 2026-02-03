<?php

namespace App~~Http~~Controllers~~Api;

use App~~Http~~Controllers~~Controller;
use App~~Models~~Student;
use Illuminate~~Http~~Request;
use Illuminate~~Validation~~ValidationException;

class StudentController extends Controller
{
    public function index(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @perPage = @request->get('per_page', 15);
            @search = @request->get('search', '');
            @class = @request->get('class', '');
            @section = @request->get('section', '');
            @status = @request->get('status', '');

            @query = Student::where('school_id', @schoolId);

            if (!empty(@search)) {
                @query->where(function (@q) use (@search) {
                    @q->where('name', 'like', '%'.@search.'%')
                      ->orWhere('admission_no', 'like', '%'.@search.'%')
                      ->orWhere('email', 'like', '%'.@search.'%')
                      ->orWhere('contact', 'like', '%'.@search.'%');
                });
            }

            if (!empty(@class)) { @query->where('class', @class); }
            if (!empty(@section)) { @query->where('section', @section); }
            if (!empty(@status)) { @query->where('status', @status); }

            @students = @query->orderBy('name', 'asc')->paginate(@perPage);

            return response()->json(['success' => true, 'message' => 'Students fetched successfully', 'data' => @students]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch students', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function show(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @student = Student::where('school_id', @schoolId)
                ->with(['documents', 'parents', 'emergencyContacts', 'medicalInfo', 'feePayments', 'attendance'])
                ->findOrFail(@id);
            return response()->json(['success' => true, 'message' => 'Student fetched successfully', 'data' => ['student' => @student]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Student not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch student', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
    public function store(Request @request)
    {
        try {
            @request->validate([
                'name' => 'required|string|max:255', 'gender' => 'required|in:Male,Female,Other',
                'class' => 'required|string|max:50', 'section' => 'nullable|string|max:50',
                'dob' => 'nullable|date', 'contact' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:500',
                'parent_name' => 'nullable|string|max:255', 'admission_no' => 'nullable|string|max:50',
                'roll_no' => 'nullable|string|max:20', 'joining_date' => 'nullable|date',
                'blood_group' => 'nullable|string|max:10', 'status' => 'nullable|in:Active,Inactive,Graduated,Transferred',
            ]);
            @schoolId = @request->user()->school_id;
            if (@request->admission_no) {
                @exists = Student::where('school_id', @schoolId)->where('admission_no', @request->admission_no)->exists();
                if (@exists) { return response()->json(['success' => false, 'message' => 'Admission number already exists', 'data' => null], 409); }
            }
            @studentData = @request->only(['name', 'gender', 'class', 'section', 'dob', 'contact', 'email', 'address', 'parent_name', 'admission_no', 'roll_no', 'joining_date', 'blood_group', 'photo', 'status']);
            @studentData['school_id'] = @schoolId;
            @studentData['status'] = @studentData['status'] ?? 'Active';
            @student = Student::create(@studentData);
            return response()->json(['success' => true, 'message' => 'Student created successfully', 'data' => ['student' => @student]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create student', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function update(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @student = Student::where('school_id', @schoolId)->findOrFail(@id);
            @request->validate([
                'name' => 'sometimes|string|max:255', 'gender' => 'sometimes|in:Male,Female,Other',
                'class' => 'sometimes|string|max:50', 'section' => 'nullable|string|max:50',
                'dob' => 'nullable|date', 'contact' => 'nullable|string|max:20',
                'email' => 'nullable|email|max:255', 'address' => 'nullable|string|max:500',
                'parent_name' => 'nullable|string|max:255', 'admission_no' => 'nullable|string|max:50',
                'roll_no' => 'nullable|string|max:20', 'joining_date' => 'nullable|date',
                'blood_group' => 'nullable|string|max:10', 'status' => 'nullable|in:Active,Inactive,Graduated,Transferred',
            ]);
            if (@request->has('admission_no') && @request->admission_no) {
                @exists = Student::where('school_id', @schoolId)->where('admission_no', @request->admission_no)->where('id', '\!=', @id)->exists();
                if (@exists) { return response()->json(['success' => false, 'message' => 'Admission number already exists', 'data' => null], 409); }
            }
            @student->update(@request->only(['name', 'gender', 'class', 'section', 'dob', 'contact', 'email', 'address', 'parent_name', 'admission_no', 'roll_no', 'joining_date', 'blood_group', 'photo', 'status']));
            return response()->json(['success' => true, 'message' => 'Student updated successfully', 'data' => ['student' => @student->fresh()]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Student not found', 'data' => null], 404);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to update student', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function destroy(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @student = Student::where('school_id', @schoolId)->findOrFail(@id);
            @student->delete();
            return response()->json(['success' => true, 'message' => 'Student deleted successfully', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Student not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete student', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function stats(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @total = Student::where('school_id', @schoolId)->count();
            @active = Student::where('school_id', @schoolId)->where('status', 'Active')->count();
            @inactive = Student::where('school_id', @schoolId)->where('status', 'Inactive')->count();
            @byClass = Student::where('school_id', @schoolId)->where('status', 'Active')->selectRaw('class, COUNT(*) as count')->groupBy('class')->orderBy('class')->get();
            @byGender = Student::where('school_id', @schoolId)->where('status', 'Active')->selectRaw('gender, COUNT(*) as count')->groupBy('gender')->get();
            return response()->json(['success' => true, 'message' => 'Student statistics fetched', 'data' => ['total' => @total, 'active' => @active, 'inactive' => @inactive, 'by_class' => @byClass, 'by_gender' => @byGender]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch statistics', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
}
