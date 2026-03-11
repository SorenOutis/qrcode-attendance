<?php

namespace App\Http\Controllers;

use App\Models\Comment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class CommentController extends Controller
{
    public function index(): Response
    {
        $from = request('from');
        $to = request('to');

        $comments = Comment::query()
            ->when($from, fn ($q) => $q->whereDate('created_at', '>=', $from))
            ->when($to, fn ($q) => $q->whereDate('created_at', '<=', $to))
            ->orderByDesc('created_at')
            ->get([
                'id',
                'name',
                'email',
                'message',
                'is_public',
                'created_at',
                'updated_at',
            ]);

        return Inertia::render('CommentsSuggestions', [
            'comments' => $comments,
            'filters' => [
                'from' => $from,
                'to' => $to,
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'message' => ['required', 'string', 'max:2000'],
        ]);

        Comment::create($data);

        return back()->with('flash', [
            'comment_submitted' => true,
        ]);
    }

    public function update(Request $request, Comment $comment): RedirectResponse
    {
        $data = $request->validate([
            'message' => ['required', 'string', 'max:2000'],
            'is_public' => ['boolean'],
        ]);

        $comment->update($data);

        return back();
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $comment->delete();

        return back();
    }
}

