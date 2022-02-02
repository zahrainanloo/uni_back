<?php

namespace App\Http\Controllers;

use App\Exam;
use App\Lesson;
use App\Question;
use Carbon\Carbon;
use App\ExamSession;
use App\Helpers\Constants;
use Illuminate\Http\Request;
use App\Services\Exam\ExamService;
use App\Http\Resources\QuizResource;
use App\Http\Resources\ResultResource;
use App\Jobs\InsertAnswers;
use App\QuestionAnswers;
use App\StudentAnswer;
use App\StudentResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\Rule;

class ExamController extends Controller
{
    function create($lessonId, Request $request)
    {
        $request->validate([
            'type' => Rule::in([1,2]),//1:formal , 2:informal
            'duration' => 'required',
            'title' => 'required',
            'startedAt' => Rule::requiredIf(function()use($request){
                return $request->input('type') == 1;
            })
        ]);
        Lesson::where('id', $lessonId)
            ->where('teacher_id', Auth::id())
            ->firstOrFail();
        try {
            $t = Exam::create([
                'lesson_id' => $lessonId,
                'teacher_id' => Auth::id(),
                'duration' => $request->duration,
                "description" => $request->description,
                'started_at' =>$request->input('type') == 1 ?  Carbon::createFromTimestamp($request->startedAt) : null,
                'title' => $request->title,
                'finished_at' => $request->input('type') == 1 ?  Carbon::createFromTimestamp($request->startedAt)->addMinutes($request->duration) : Carbon::now()->addMinutes($request->duration)->toDateTimeString()
            ]);
            return $this->respondWithTemplate(true, [], 'امتحان ثبت شد');
        } catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }
    function selectExamQuestions($lessonId, $examId, Request $request)
    {

        $request->validate([
            'questions' => 'array|' . Rule::requiredIf(function()use($request){
                    return $request->input('type') == 1;
            }),
            'count' => Rule::requiredIf(function()use($request){
                return $request->input('type') == 2;
            }),
            'type' => Rule::in([1,2]) //1 : general order , 2: random order
        ]);

        $exam = Exam::where('lesson_id', $lessonId)->where('id', $examId)->firstOrFail();
        try {
            if ($request->input('type') == 1) {
                $questionIds = Question::whereIn('id', $request->questions)
                    ->where('is_accepted', 1)
                    ->pluck('id');

            }else{
                $questionIds = Question::where('is_accepted',1)
                    ->inRandomOrder()
                    ->limit($request->input('count'))
                    ->pluck('id');
            }
            $exam->questions()->syncWithoutDetaching($questionIds);
            return $this->respondWithTemplate(true, [], 'سوالات امتحان ثبت شد');

        } catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }


    }


    function getAll(Request $request)
    {

        $exams = Exam::query()->with('teacher');
        try {
            $exams->when($request->title,function($q) use($request){
                return $q->where('title','like','%'. $request->title. '%');
        });
        $data= $exams->orderBy('created_at',$request->order??'desc')
        ->paginate($request->perPage??20);
        return $this->respondWithTemplate(true, $data);
        }
        catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }

    //teacher only
    function getById()
    {
        $exam = Exam::whereId(1)
            ->with(['questions.answers', 'lesson'])
            ->firstOrFail();
        $data = QuizResource::collection($exam);

        return $this->respondWithTemplate(true, $data);
    }
    function start($examId)
    {
        $exam = Exam::whereId($examId)
            ->with(['questions.answers', 'lesson'])
            ->firstOrFail();
        try {
            $service = new ExamService($exam, Auth::id());
            $service->checkExamAvailability();
            ExamSession::create([
                'student_id' => Auth::id(),
                'exam_id' => $examId,
                'started_at' => now()
            ]);
            $data = new QuizResource($exam);
            return $this->respondWithTemplate(true, $data);
        } catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }
    function finish($examId, Request $request)
    {
        $request->validate([
            'answers' => 'required|array'
        ]);
        $exam = Exam::whereId($examId)
            ->firstOrFail();

        $examSession = ExamSession::where('exam_id', $examId)
            ->where('student_id', Auth::id())
            ->firstOrFail();

        try {
            $service = new ExamService($exam, Auth::id());
            $service->canUserFinishExam();
            $examSession->update([
                'finished_at' => now()
            ]);

            dispatch(new InsertAnswers($request->answers, Auth::id(), $examId));
            return $this->respondWithTemplate(true, [], 'امتحان شما با موفقیت ثبت شد');
        } catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }

    public function result($examId)
    {
        try {
            $result = StudentResult::where('student_id', Auth::id())
                ->with(['exam.teacher','exam.questions'])
                ->where('exam_id', $examId)->first();
            $data = new ResultResource($result);
            return $this->respondWithTemplate(true, $data);
        } catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }
    public function showResultByTeacher($examId,$userId)
    {
        try {
            $result = StudentResult::where('student_id', $userId)
            ->with(['exam.teacher','exam.questions'])
            ->where('exam_id', $examId)->firstOrFail();
            $data = new ResultResource($result);
            return $this->respondWithTemplate(true, $data);
        } catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }
    public function showExamStudentsByTeacher($examId){
        try {
            $result = StudentResult::where('exam_id', $examId)->firstOrFail();
            $data = new ResultResource($result);
            return $this->respondWithTemplate(true, $data);
        } catch (\Exception $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }

    public function allResults()
    {
        try {
            $results = StudentResult::where('student_id', auth()->id())->paginate(10);
            $data =  ResultResource::collection($results);
            return $this->respondWithTemplate(true, $data);
        } catch (\Throwable $e) {
            return $this->respondWithTemplate(false, [], $e->getMessage());
        }
    }
}
