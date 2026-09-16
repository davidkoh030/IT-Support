<?php

namespace App\Http\Controllers;

use App\Models\KnowledgeArticle;
use Illuminate\Http\Request;

class KnowledgeArticleController extends Controller
{
    public function index(Request $request)
    {
        $query = KnowledgeArticle::query()->where('status', 'published');

        if ($search = $request->query('q')) {
            $query->where(function ($q) use ($search) {
                $q->where('title', 'like', "%{$search}%")->orWhere('body', 'like', "%{$search}%");
            });
        }

        $articles = $query->orderBy('title')->paginate(15)->withQueryString();

        return view('knowledge.index', compact('articles'));
    }

    public function show(KnowledgeArticle $knowledgeArticle)
    {
        $this->authorize('view', $knowledgeArticle);

        return view('knowledge.show', ['article' => $knowledgeArticle]);
    }
}
