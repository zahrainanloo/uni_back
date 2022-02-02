<?php

namespace App\Http\Resources;

use App\StudentAnswer;
use Illuminate\Http\Resources\Json\JsonResource;

class ResultResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->exam->id,
            'url' => $this->getUrl(),
            'result' => $this->result,
            'exam' => $this->exam->title,
            'questions' => $this->getQuestions(),
            'teacher' => $this->exam->teacher->name,
            'students' => $this->getStudents(),
            'startedAt' => $this->exam->started_at->timestamp
            // 'finishedAt' => $this->finished_at->timestamp
        ];
    }

    public function getQuestions()
    {
        $questions = $this->exam->questions;
        $data = [];
        foreach($questions as $question)
        {
            $data[] = [
                'id' => $question->id,
                'question' => $question->question_text,
                'answers' =>$this->getAnswers($question),
                'student_answer' => $this->getStudentAnswers($question->id),
            ];
        }
        return $data;
    }

    public function getStudentAnswers($question_id)
    {
        $student_answer = StudentAnswer::where('exam_id',$this->exam->id)->where('question_id',$question_id)->first();
        return [
            'answer'=>$student_answer->answer ?? null,
            'hash' => $student_answer->answer_hash ?? null
        ];
    }

    public function getUrl()
    {
        return route('exam.result',['examId'=>$this->exam->id]);
    }

    public function getStudents()
    {
        $student_results = $this->exam->students;
        $data = [];
        foreach($student_results as $student_result)
        {
            $data[] = new UserResource($student_result->student);
        }
        return $data;
    }

    public function getAnswers($question)
    {
        $data = [];
        foreach($question->answers as $answer)
        {
            $data[] =  [
                'text' => $answer->answer,
                'is_correct' => $answer->is_correct,
                'hash' => $answer->hash
            ];
        }
        return $data;

    }
}
