<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Facility;
use Illuminate\Http\Request;

class CategoryController extends Controller
{
    public function index()
    {
        $categories = Category::with(['facilities' => function ($q) {
                $q->orderBy('name');
            }])
            ->orderBy('name')
            ->get();

        return view('admin.catalog.index', compact('categories'));
    }

    public function create()
    {
        return view('admin.categories.create');
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:categories,name'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category = Category::create($data);

        return redirect()
            ->route('admin.categories.index')
            ->withFragment('category-' . $category->id)
            ->with('status', 'Category created successfully.');
    }

    public function show(Category $category)
    {
        // Not used — redirect to edit
        return redirect()->route('admin.categories.edit', $category);
    }

    public function edit(Category $category)
    {
        return view('admin.categories.edit', compact('category'));
    }

    public function update(Request $request, Category $category)
    {
        $data = $request->validate([
            'name'        => ['required', 'string', 'max:255', 'unique:categories,name,' . $category->id],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $category->update($data);

        return redirect()
            ->route('admin.categories.index')
            ->withFragment('category-' . $category->id)
            ->with('status', 'Category updated successfully.');
    }

    public function destroy(Category $category)
    {
        // Refuse to delete if facilities are attached — the DB cascade would silently
        // wipe them, which is almost certainly not what the admin wants.
        if ($category->facilities()->exists()) {
            return back()->with('error',
                'Cannot delete "'.$category->name.'" — it still has facilities. '.
                'Move or delete those facilities first.');
        }

        $category->delete();

        return redirect()
            ->route('admin.categories.index')
            ->with('status', 'Category deleted.');
    }
}
