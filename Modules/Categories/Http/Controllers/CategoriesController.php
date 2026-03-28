<?php

namespace Modules\Categories\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Modules\Categories\Http\Requests\CategoryRequest;
use Modules\Categories\Services\CategoryService;
use Modules\Categories\Models\Category;
use Yajra\DataTables\DataTables;
use App\Traits\ModuleTrait;

class CategoriesController extends Controller
{
    protected string $exportClass = '\App\Exports\CategoriesExport';
    protected $categoryService;

    use ModuleTrait {
        initializeModuleTrait as private traitInitializeModuleTrait;
    }

    public function __construct(CategoryService $categoryService)
    {
        $this->categoryService = $categoryService;
        $this->traitInitializeModuleTrait(
            'categories.title',
            'categories',
            'fa-solid fa-tag'
        );
    }

    public function index(Request $request)
    {
        $module_action = 'List';

        $filter = [
            'status' => $request->status,
        ];

        return view('categories::backend.categories.index', compact('module_action', 'filter'));
    }

    public function index_data(DataTables $datatable, Request $request)
    {
        $filter = $request->filter ?? [];
        return $this->categoryService->getDataTable($datatable, $filter);
    }

    public function update_status(Request $request, int $id)
    {
        $this->categoryService->updateCategory($id, ['status' => $request->status]);
        return response()->json(['status' => true, 'message' => __('messages.lbl_status') . ' updated']);
    }

    public function bulk_action(Request $request)
    {
        $ids = explode(',', $request->rowIds);
        $actionType = $request->action_type;
        $moduleName = 'Categories';
        return $this->performBulkAction(Category::class, $ids, $actionType, $moduleName);
    }

    public function create(Request $request)
    {
        $module_title = 'Add Category';
        $page_type = 'categories';
        return view('categories::backend.categories.create', compact('module_title', 'page_type'));
    }

    public function store(CategoryRequest $request)
    {
        $data = $request->all();
        $data['file_url'] = extractFileNameFromUrl($data['file_url'] ?? '', 'categories');
        $this->categoryService->createCategory($data);
        $message = 'Category created successfully.';
        return redirect()->route('backend.categories.index')->with('success', $message);
    }

    public function show(int $id)
    {
        return redirect()->route('backend.categories.edit', $id);
    }

    public function edit(int $id)
    {
        $category = $this->categoryService->getCategoryById($id);
        $module_title = 'Edit Category';
        $page_type = 'categories';
        return view('categories::backend.categories.edit', compact('category', 'module_title', 'page_type'));
    }

    public function update(CategoryRequest $request, int $id)
    {
        $data = $request->all();
        $data['file_url'] = extractFileNameFromUrl($data['file_url'] ?? '', 'categories');
        $this->categoryService->updateCategory($id, $data);
        $message = 'Category updated successfully.';
        return redirect()->route('backend.categories.index')->with('success', $message);
    }

    public function destroy(int $id)
    {
        $this->categoryService->deleteCategory($id);
        return response()->json(['message' => 'Category deleted.', 'status' => true], 200);
    }

    public function restore(int $id)
    {
        $this->categoryService->restoreCategory($id);
        return response()->json(['message' => 'Category restored.', 'status' => true], 200);
    }

    public function forceDelete(int $id)
    {
        $this->categoryService->forceDeleteCategory($id);
        return response()->json(['message' => 'Category permanently deleted.', 'status' => true], 200);
    }
}
