<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\KnowledgeArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KnowledgeArticleController extends Controller
{
    public function index()
    {
        $this->authorize('manage', KnowledgeArticle::class);

        return view('admin.knowledge-articles.index', ['articles' => KnowledgeArticle::orderBy('title')->get()]);
    }

    public function create()
    {
        $this->authorize('manage', KnowledgeArticle::class);

        return view('admin.knowledge-articles.form', ['article' => new KnowledgeArticle]);
    }

    public function store(Request $request)
    {
        $this->authorize('manage', KnowledgeArticle::class);

        $data = $this->validated($request);
        $data['slug'] = Str::slug($data['title']).'-'.Str::random(4);
        $data['owner_id'] = $request->user()->id;

        KnowledgeArticle::create($data);

        return redirect()->route('admin.knowledge-articles.index')->with('status', 'Article created.');
    }

    public function edit(KnowledgeArticle $knowledgeArticle)
    {
        $this->authorize('manage', KnowledgeArticle::class);

        return view('admin.knowledge-articles.form', ['article' => $knowledgeArticle]);
    }

    public function update(Request $request, KnowledgeArticle $knowledgeArticle)
    {
        $this->authorize('manage', KnowledgeArticle::class);
        $knowledgeArticle->update($this->validated($request));

        return redirect()->route('admin.knowledge-articles.index')->with('status', 'Article updated.');
    }

    public function destroy(KnowledgeArticle $knowledgeArticle)
    {
        $this->authorize('manage', KnowledgeArticle::class);
        $knowledgeArticle->delete();

        return redirect()->route('admin.knowledge-articles.index')->with('status', 'Article deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'body' => ['required', 'string', 'max:10000'],
            'audience' => ['nullable', 'string', 'max:150'],
            'review_date' => ['nullable', 'date'],
            'status' => ['required', 'in:draft,published'],
        ]);
    }
}
