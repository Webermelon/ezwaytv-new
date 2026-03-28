<?php

namespace Modules\Categories\Repositories;

use Modules\Categories\Models\Category;
use Auth;

class CategoryRepository implements CategoryRepositoryInterface
{
    public function all()
    {
        return Category::where('status', 1)->orderBy('name')->get();
    }

    public function find(int $id)
    {
        $query = Category::query();

        if (Auth::user()->hasRole('user')) {
            $query->whereNull('deleted_at');
        }

        $category = $query->withTrashed()->findOrFail($id);
        $category->file_url = setBaseUrlWithFileName($category->file_url, 'image', 'categories');

        return $category;
    }

    public function create(array $data)
    {
        return Category::create($data);
    }

    public function update(int $id, array $data)
    {
        $category = Category::findOrFail($id);
        $category->update($data);
        return $category;
    }

    public function delete(int $id)
    {
        $category = Category::findOrFail($id);
        $category->delete();
        return $category;
    }

    public function restore(int $id)
    {
        $category = Category::withTrashed()->findOrFail($id);
        $category->restore();
        return $category;
    }

    public function forceDelete(int $id)
    {
        $category = Category::withTrashed()->findOrFail($id);
        $category->forceDelete();
        return $category;
    }

    public function query()
    {
        $query = Category::query()->withTrashed();

        if (Auth::user()->hasRole('user')) {
            $query->whereNull('deleted_at');
        }

        return $query;
    }

    public function list(int $perPage, string $searchTerm = null)
    {
        $query = Category::query();

        if ($searchTerm) {
            $query->where('name', 'like', "%{$searchTerm}%");
        }

        return $query->where('status', 1)->whereNull('deleted_at')->orderBy('name')->paginate($perPage);
    }
}
