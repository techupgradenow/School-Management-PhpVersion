<?php

namespace App~~Http~~Controllers~~Api;

use App~~Http~~Controllers~~Controller;
use App~~Models~~ClassModel;
use App~~Models~~Section;
use App~~Models~~Subject;
use App~~Models~~Student;
use Illuminate~~Http~~Request;
use Illuminate~~Validation~~ValidationException;

class AcademicController extends Controller
{
    public function getClasses(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @classes = ClassModel::where('school_id', @schoolId)->withCount('sections')->orderBy('name')->get();
            return response()->json(['success' => true, 'message' => 'Classes fetched successfully', 'data' => ['classes' => @classes]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch classes', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function addClass(Request @request)
    {
        try {
            @request->validate(['name' => 'required|string|max:100', 'description' => 'nullable|string|max:255', 'display_order' => 'nullable|integer']);
            @schoolId = @request->user()->school_id;
            @exists = ClassModel::where('school_id', @schoolId)->where('name', @request->name)->exists();
            if (@exists) { return response()->json(['success' => false, 'message' => 'Class already exists', 'data' => null], 409); }
            @class = ClassModel::create(['school_id' => @schoolId, 'name' => @request->name, 'description' => @request->description, 'display_order' => @request->display_order ?? 0, 'is_active' => true]);
            return response()->json(['success' => true, 'message' => 'Class created successfully', 'data' => ['class' => @class]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create class', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function updateClass(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @class = ClassModel::where('school_id', @schoolId)->findOrFail(@id);
            @request->validate(['name' => 'sometimes|string|max:100', 'description' => 'nullable|string|max:255', 'display_order' => 'nullable|integer', 'is_active' => 'nullable|boolean']);
            if (@request->has('name')) {
                @exists = ClassModel::where('school_id', @schoolId)->where('name', @request->name)->where('id', '\!=', @id)->exists();
                if (@exists) { return response()->json(['success' => false, 'message' => 'Class name already exists', 'data' => null], 409); }
            }
            @class->update(@request->only(['name', 'description', 'display_order', 'is_active']));
            return response()->json(['success' => true, 'message' => 'Class updated successfully', 'data' => ['class' => @class->fresh()]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Class not found', 'data' => null], 404);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to update class', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function deleteClass(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @class = ClassModel::where('school_id', @schoolId)->findOrFail(@id);
            @studentCount = Student::where('school_id', @schoolId)->where('class', @class->name)->count();
            if (@studentCount > 0) { return response()->json(['success' => false, 'message' => @studentCount . ' student(s) are assigned to this class', 'data' => null], 409); }
            @class->delete();
            return response()->json(['success' => true, 'message' => 'Class deleted successfully', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Class not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete class', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function getSections(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @classId = @request->get('class_id', '');
            @query = Section::where('school_id', @schoolId);
            if (\!empty(@classId)) { @query->where('class_id', @classId); }
            @sections = @query->orderBy('name')->get();
            return response()->json(['success' => true, 'message' => 'Sections fetched successfully', 'data' => ['sections' => @sections]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch sections', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function addSection(Request @request)
    {
        try {
            @request->validate(['name' => 'required|string|max:50', 'class_id' => 'required|integer|exists:classes,id']);
            @schoolId = @request->user()->school_id;
            @exists = Section::where('school_id', @schoolId)->where('class_id', @request->class_id)->where('name', @request->name)->exists();
            if (@exists) { return response()->json(['success' => false, 'message' => 'Section already exists for this class', 'data' => null], 409); }
            @section = Section::create(['school_id' => @schoolId, 'class_id' => @request->class_id, 'name' => @request->name, 'is_active' => true]);
            return response()->json(['success' => true, 'message' => 'Section created successfully', 'data' => ['section' => @section]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create section', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function deleteSection(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @section = Section::where('school_id', @schoolId)->findOrFail(@id);
            @section->delete();
            return response()->json(['success' => true, 'message' => 'Section deleted successfully', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Section not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete section', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function getSubjects(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @subjects = Subject::where('school_id', @schoolId)->orderBy('name')->get();
            return response()->json(['success' => true, 'message' => 'Subjects fetched successfully', 'data' => ['subjects' => @subjects]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch subjects', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function addSubject(Request @request)
    {
        try {
            @request->validate(['name' => 'required|string|max:100', 'code' => 'nullable|string|max:20', 'description' => 'nullable|string|max:255']);
            @schoolId = @request->user()->school_id;
            @exists = Subject::where('school_id', @schoolId)->where('name', @request->name)->exists();
            if (@exists) { return response()->json(['success' => false, 'message' => 'Subject already exists', 'data' => null], 409); }
            @subject = Subject::create(['school_id' => @schoolId, 'name' => @request->name, 'code' => @request->code, 'description' => @request->description, 'is_active' => true]);
            return response()->json(['success' => true, 'message' => 'Subject created successfully', 'data' => ['subject' => @subject]], 201);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to create subject', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function updateSubject(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @subject = Subject::where('school_id', @schoolId)->findOrFail(@id);
            @request->validate(['name' => 'sometimes|string|max:100', 'code' => 'nullable|string|max:20', 'description' => 'nullable|string|max:255', 'is_active' => 'nullable|boolean']);
            @subject->update(@request->only(['name', 'code', 'description', 'is_active']));
            return response()->json(['success' => true, 'message' => 'Subject updated successfully', 'data' => ['subject' => @subject->fresh()]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Subject not found', 'data' => null], 404);
        } catch (ValidationException @e) {
            return response()->json(['success' => false, 'message' => 'Validation failed', 'data' => null, 'errors' => @e->errors()], 422);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to update subject', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function deleteSubject(Request @request, @id)
    {
        try {
            @schoolId = @request->user()->school_id;
            @subject = Subject::where('school_id', @schoolId)->findOrFail(@id);
            @subject->delete();
            return response()->json(['success' => true, 'message' => 'Subject deleted successfully', 'data' => ['id' => @id]]);
        } catch (~~Illuminate~~Database~~Eloquent~~ModelNotFoundException @e) {
            return response()->json(['success' => false, 'message' => 'Subject not found', 'data' => null], 404);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to delete subject', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
}
