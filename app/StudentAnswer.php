<?php

namespace App;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StudentAnswer extends Model
{
    use HasFactory;
    protected $fillable = [
        'exam_id',
        'question_id',
        'answer',
        'answer_hash'
    ];


    public function exam()
    {
        return $this->belongsTo(Exam::class,'exam_id','id');
    }

    public function question()
    {
        return $this->belongsTo(Question::class,'question_id','id');
    }

}
