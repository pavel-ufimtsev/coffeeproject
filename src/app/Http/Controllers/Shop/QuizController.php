<?php

namespace App\Http\Controllers\Shop;

use App\Http\Controllers\Controller;
use App\Services\Catalog\CoffeeQuiz;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * "Which coffee suits me": the questions, and once all are answered the
 * best matches. Answers travel in the query string, so a result can be
 * shared and reloaded; result pages are not indexed.
 */
class QuizController extends Controller
{
    public function __invoke(Request $request, CoffeeQuiz $quiz): View
    {
        $answers = $quiz->answers($request->query());

        return view('shop.quiz', [
            'questions' => $quiz->questions(),
            'answers' => $answers,
            'matches' => $answers ? $quiz->recommend($answers) : collect(),
            'grind' => $answers && $answers['form'] === 'ground' ? (CoffeeQuiz::GRINDS[$answers['brew']] ?? null) : null,
        ]);
    }
}
