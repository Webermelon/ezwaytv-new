<?php

namespace Modules\Categories\Services;

use Modules\Categories\Repositories\CategoryRepositoryInterface;
use Illuminate\Support\Str;
use Yajra\DataTables\DataTables;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;

class CategoryService
{
    protected $categoryRepository;

    public function __construct(CategoryRepositoryInterface $categoryRepository)
    {
        $this->categoryRepository = $categoryRepository;
    }

    public function getAllCategories()
    {
        return $this->categoryRepository->all();
    }

    public function getCategoryById(int $id)
    {
        return $this->categoryRepository->find($id);
    }

    protected function clearCategoryCache()
    {
        Cache::forget('categories');
        Cache::forget('categories_v2');
    }

    public function createCategory(array $data)
    {
        $this->clearCategoryCache();
        $data['slug'] = Str::slug($data['name']);
        return $this->categoryRepository->create($data);
    }

    public function updateCategory(int $id, array $data)
    {
        $this->clearCategoryCache();
        return $this->categoryRepository->update($id, $data);
    }

    public function deleteCategory(int $id)
    {
        $this->clearCategoryCache();
        return $this->categoryRepository->delete($id);
    }

    public function restoreCategory($id)
    {
        $this->clearCategoryCache();
        return $this->categoryRepository->restore($id);
    }

    public function forceDeleteCategory($id)
    {
        $this->clearCategoryCache();
        return $this->categoryRepository->forceDelete($id);
    }

    public function getDataTable(DataTables $datatable, array $filter)
    {
        $query = $this->getFilteredData($filter);

        return $datatable->eloquent($query)
            ->addColumn('check', function ($row) {
                return '<input type="checkbox" class="form-check-input select-table-row" id="datatable-row-' . $row->id . '" name="datatable_ids[]" value="' . $row->id . '" data-type="categories" onclick="dataTableRowCheck(' . $row->id . ',this)">';
            })
            ->editColumn('image', function ($data) {
                $imageUrl = setBaseUrlWithFileName($data->file_url, 'image', 'categories');
                return view('components.image-name', ['image' => $imageUrl, 'name' => $data->name])->render();
            })
            ->addColumn('action', function ($data) {
                return view('categories::backend.categories.action', compact('data'));
            })
            ->editColumn('status', function ($row) {
                $checked  = $row->status ? 'checked="checked"' : '';
                $disabled = $row->trashed() ? 'disabled' : '';
                return '
                    <div class="form-check form-switch">
                        <input type="checkbox" data-url="' . route('backend.categories.update_status', $row->id) . '"
                            data-token="' . csrf_token() . '" class="switch-status-change form-check-input"
                            id="datatable-row-' . $row->id . '" name="status" value="' . $row->id . '" ' . $checked . ' ' . $disabled . '>
                    </div>
                ';
            })
            ->editColumn('updated_at', function ($data) {
                $diff = Carbon::now()->diffInHours($data->updated_at);
                return $diff < 25 ? $data->updated_at->diffForHumans() : $data->updated_at->isoFormat('llll');
            })
            ->orderColumns(['id'], '-:column $1')
            ->rawColumns(['action', 'status', 'check', 'image'])
            ->toJson();
    }

    public function getFilteredData(array $filter)
    {
        $query = $this->categoryRepository->query();

        if (isset($filter['column_status'])) {
            $query->where('status', $filter['column_status']);
        }

        if (isset($filter['name'])) {
            $query->where('name', 'like', '%' . $filter['name'] . '%');
        }

        return $query;
    }
}
