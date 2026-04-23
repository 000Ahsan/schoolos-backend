<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\AiQueryService;
use Illuminate\Http\Request;

class AiChatController extends Controller
{
    protected $aiService;

    public function __construct(AiQueryService $aiService)
    {
        $this->aiService = $aiService;
    }

    /**
     * Handle AI chat questions.
     */
    public function ask(Request $request)
    {
        $request->validate([
            'question' => 'required|string|max:500',
        ]);

        $question = $request->input('question');
        $response = $this->aiService->handle($question);

        return response()->json($response);
    }
}
