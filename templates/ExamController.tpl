<?php

namespace App~~Http~~Controllers~~Api;

use App~~Http~~Controllers~~Controller;
use App~~Models~~Exam;
use App~~Models~~ExamMark;
use Illuminate~~Http~~Request;
use Illuminate~~Validation~~ValidationException;
use Illuminate~~Support~~Facades~~DB;

class ExamController extends Controller
{
    public function index(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @perPage = @request->get('per_page', 15);
            @class = @request->get('class', '');
            @status = @request->get('status', '');
            @query = Exam::where('school_id', @schoolId);
            if (\!empty(@class)) { @query->where('class', @class); }
            if (\!empty(@status)) { @query->where('status', @status); }
            @exams = @query->orderBy('exam_date', 'desc')->paginate(@perPage);
            return response()->json(['success' => true, 'message' => 'Exams fetched successfully', 'data' => @exams]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch exams', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function show(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @exam = Exam::where('school_id', @schoolId)->with('marks.student')->findOrFail(@id);
            return response()->json(['success' => true, 'message' => 'Exam fetched successfully', 'data' => ['exam' => @exam]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Exam not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch exam', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function store(Request @request)
    {
        try {
            @request->validate([
                'name' => 'required|string|max:255', 'class' => 'required|string|max:50',
                'subject_id' => 'required|integer', 'exam_date' => 'required|date',
                'start_time' => 'nullable|string', 'end_time' => 'nullable|string',
                'max_marks' => 'required|numeric|min:1', 'pass_marks' => 'required|numeric|min:0',
                'description' => 'nullable|string|max:500', 'status' => 'nullable|in:Scheduled,Completed,Cancelled',
            ]);
            @schoolId = @request->user()->school_id;
            @examData = @request->only(['name', 'class', 'subject_id', 'exam_date', 'start_time', 'end_time', 'max_marks', 'pass_marks', 'description', 'status']);
            @examData['school_id'] = @schoolId;
            @examData['status'] = @examData['status'] ?? 'Scheduled';
            @exam = Exam::create(@examData);
            return response()->json(['success' => true, 'message' => 'Exam created successfully', 'data' => ['exam' => @exam]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create exam', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function update(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @exam = Exam::where('school_id', @schoolId)->findOrFail(@id);
            @request->validate([
                'name' => 'sometimes|string|max:255', 'class' => 'sometimes|string|max:50',
                'subject_id' => 'sometimes|integer', 'exam_date' => 'sometimes|date',
                'max_marks' => 'sometimes|numeric|min:1', 'pass_marks' => 'sometimes|numeric|min:0',
                'description' => 'nullable|string|max:500', 'status' => 'nullable|in:Scheduled,Completed,Cancelled',
                'marks' => 'nullable|array', 'marks.*.student_id' => 'required_with:marks|integer', 'marks.*.marks_obtained' => 'required_with:marks|numeric|min:0',
            ]);
            @exam->update(@request->only(['name', 'class', 'subject_id', 'exam_date', 'start_time', 'end_time', 'max_marks', 'pass_marks', 'description', 'status']));
            if (@request->has('marks') && is_array(@request->marks)) {
                foreach (@request->marks as @mark) {
                    ExamMark::updateOrCreate(['exam_id' => @id, 'student_id' => @mark['student_id']], ['marks_obtained' => @mark['marks_obtained'], 'remarks' => @mark['remarks'] ?? null, 'entered_by' => @request->user()->id]);
                }
            }
            return response()->json(['success' => true, 'message' => 'Exam updated successfully', 'data' => ['exam' => @exam->fresh()]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Exam not found', 'data' => null], 404);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to update exam', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function destroy(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @exam = Exam::where('school_id', @schoolId)->findOrFail(@id);
            @exam->marks()->delete();
            @exam->delete();
            return response()->json(['success' => true, 'message' => 'Exam deleted successfully', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Exam not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete exam', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function stats(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @total = Exam::where('school_id', @schoolId)->count();
            @scheduled = Exam::where('school_id', @schoolId)->where('status', 'Scheduled')->count();
            @completed = Exam::where('school_id', @schoolId)->where('status', 'Completed')->count();
            return response()->json(['success' => true, 'message' => 'Exam statistics fetched', 'data' => ['total' => @total, 'scheduled' => @scheduled, 'completed' => @completed]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch statistics', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
}
