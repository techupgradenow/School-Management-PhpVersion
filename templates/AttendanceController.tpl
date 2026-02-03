<?php

namespace App~~Http~~Controllers~~Api;

use App~~Http~~Controllers~~Controller;
use App~~Models~~Attendance;
use App~~Models~~Student;
use Illuminate~~Http~~Request;
use Illuminate~~Support~~Facades~~DB;

class AttendanceController extends Controller
{
    public function index(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @date = @request->get('date', date('Y-m-d'));
            @class = @request->get('class', '');
            @section = @request->get('section', '');
            @status = @request->get('status', '');

            @query = Attendance::where('attendance.school_id', @schoolId)->whereDate('attendance.date', @date)
                ->leftJoin('students', function (@join) { @join->on('attendance.student_id', '=', 'students.id')->on('students.school_id', '=', 'attendance.school_id'); })
                ->select('attendance.*', 'students.name as student_name', 'students.class', 'students.section', 'students.roll_no');

            if (\!empty(@class)) { @query->where('students.class', @class); }
            if (\!empty(@section)) { @query->where('students.section', @section); }
            if (\!empty(@status)) { @query->where('attendance.status', @status); }

            @records = @query->orderBy('students.class')->orderBy('students.section')->orderBy('students.roll_no')->get();
            return response()->json(['success' => true, 'message' => 'Attendance records fetched successfully', 'data' => ['records' => @records, 'date' => @date]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch attendance', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function store(Request @request)
    {
        try {
            @request->validate(['records' => 'required|array', 'records.*.student_id' => 'required|integer', 'records.*.status' => 'required|in:Present,Absent,Late', 'date' => 'nullable|date']);
            @schoolId = @request->user()->school_id;
            @date = @request->get('date', date('Y-m-d'));
            @markedBy = @request->user()->id;

            DB::beginTransaction();
            @successCount = 0;
            @errors = [];

            foreach (@request->records as @record) {
                @existing = Attendance::where('student_id', @record['student_id'])->where('school_id', @schoolId)->whereDate('date', @date)->first();
                if (@existing) {
                    @existing->update(['status' => @record['status'], 'remarks' => @record['remarks'] ?? null, 'marked_by' => @markedBy]);
                } else {
                    Attendance::create(['student_id' => @record['student_id'], 'school_id' => @schoolId, 'date' => @date, 'status' => @record['status'], 'remarks' => @record['remarks'] ?? null, 'marked_by' => @markedBy]);
                }
                @successCount++;
            }

            DB::commit();
            return response()->json(['success' => true, 'message' => 'Attendance marked for ' . @successCount . ' student(s)', 'data' => ['success_count' => @successCount, 'errors' => @errors]]);
        } catch (~~Exception @e) {
            DB::rollBack();
            return response()->json(['success' => false, 'message' => 'Failed to mark attendance', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function studentAttendance(Request @request, @studentId)
    {
        try {
            @schoolId = @request->user()->school_id;
            @startDate = @request->get('start_date', date('Y-m-d', strtotime('-30 days')));
            @endDate = @request->get('end_date', date('Y-m-d'));
            @records = Attendance::where('student_id', @studentId)->where('school_id', @schoolId)->whereBetween('date', [@startDate, @endDate])->orderBy('date', 'desc')->get();
            return response()->json(['success' => true, 'message' => 'Student attendance fetched', 'data' => ['student_id' => @studentId, 'records' => @records, 'start_date' => @startDate, 'end_date' => @endDate]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch student attendance', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function stats(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @date = @request->get('date', date('Y-m-d'));
            @result = Attendance::where('school_id', @schoolId)->whereDate('date', @date)
                ->selectRaw('COUNT(DISTINCT student_id) as total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late')
                ->first();
            @total = (int)@result->total;
            @present = (int)@result->present;
            @percentage = @total > 0 ? round((@present / @total) * 100, 2) : 0;
            return response()->json(['success' => true, 'message' => 'Statistics fetched', 'data' => ['date' => @date, 'total' => @total, 'present' => @present, 'absent' => (int)@result->absent, 'late' => (int)@result->late, 'percentage' => @percentage]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to fetch statistics', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }

    public function report(Request @request)
    {
        try {
            @schoolId = @request->user()->school_id;
            @startDate = @request->get('start_date', date('Y-m-01'));
            @endDate = @request->get('end_date', date('Y-m-t'));
            @report = Attendance::where('school_id', @schoolId)->whereBetween('date', [@startDate, @endDate])
                ->selectRaw('DATE(date) as date, COUNT(*) as total, SUM(CASE WHEN status = 'Present' THEN 1 ELSE 0 END) as present, SUM(CASE WHEN status = 'Absent' THEN 1 ELSE 0 END) as absent, SUM(CASE WHEN status = 'Late' THEN 1 ELSE 0 END) as late')
                ->groupByRaw('DATE(date)')->orderBy('date', 'asc')->get();
            return response()->json(['success' => true, 'message' => 'Report generated successfully', 'data' => ['report' => @report, 'start_date' => @startDate, 'end_date' => @endDate]]);
        } catch (~~Exception @e) {
            return response()->json(['success' => false, 'message' => 'Failed to generate report', 'data' => null, 'errors' => ['server' => @e->getMessage()]], 500);
        }
    }
}
