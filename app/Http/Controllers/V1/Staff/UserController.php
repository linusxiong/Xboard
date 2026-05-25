<?php

namespace App\Http\Controllers\V1\Staff;

use App\Exceptions\ApiException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserSendMail;
use App\Http\Requests\Staff\UserUpdate;
use App\Jobs\SendEmailJob;
use App\Models\Plan;
use App\Models\User;
use Illuminate\Http\Request;

class UserController extends Controller
{
    private const USER_FILTER_KEYS = [
        'id',
        'email',
        'transfer_enable',
        'd',
        'expired_at',
        'uuid',
        'token',
        'invite_by_email',
        'invite_user_id',
        'plan_id',
        'banned',
        'remarks',
    ];

    private const USER_SORT_COLUMNS = [
        'id',
        'email',
        'transfer_enable',
        'u',
        'd',
        'expired_at',
        'balance',
        'commission_balance',
        'invite_user_id',
        'plan_id',
        'banned',
        'created_at',
        'updated_at',
    ];

    public function getUserInfoById(Request $request)
    {
        if (empty($request->input('id'))) {
            return $this->fail([422,'用户ID不能为空']);
        }
        $user = User::where('is_admin', 0)
            ->where('id', $request->input('id'))
            ->where('is_staff', 0)
            ->first();
        if (!$user) return $this->fail([400202,'用户不存在']);
        return $this->success($user);
    }

    public function update(UserUpdate $request)
    {
        $params = $request->validated();
        $user = User::find($request->input('id'));
        if (!$user) {
            return $this->fail([400202,'用户不存在']);
        }
        if (User::where('email', $params['email'])->first() && $user->email !== $params['email']) {
            return $this->fail([400201,'邮箱已被使用']);
        }
        if (isset($params['password'])) {
            $params['password'] = password_hash($params['password'], PASSWORD_DEFAULT);
            $params['password_algo'] = NULL;
        } else {
            unset($params['password']);
        }
        if (isset($params['plan_id'])) {
            $plan = Plan::find($params['plan_id']);
            if (!$plan) {
                return $this->fail([400202,'订阅不存在']);
            }
            $params['group_id'] = $plan->group_id;
        }

        try {
            $user->update($params);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500,'更新失败']);
        }
        return $this->success(true);
    }

    public function sendMail(UserSendMail $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $this->getSortColumn($request->input('sort'), 'created_at');
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        $users = $builder->get();
        foreach ($users as $user) {
            SendEmailJob::dispatch([
                'email' => $user->email,
                'subject' => $request->input('subject'),
                'template_name' => 'notify',
                'template_value' => [
                    'name' => admin_setting('app_name', 'XBoard'),
                    'url' => admin_setting('app_url'),
                    'content' => $request->input('content')
                ]
            ]);
        }

        return $this->success(true);
    }

    public function ban(Request $request)
    {
        $sortType = in_array($request->input('sort_type'), ['ASC', 'DESC']) ? $request->input('sort_type') : 'DESC';
        $sort = $this->getSortColumn($request->input('sort'), 'created_at');
        $builder = User::orderBy($sort, $sortType);
        $this->filter($request, $builder);
        try {
            $builder->update([
                'banned' => 1
            ]);
        } catch (\Exception $e) {
            \Log::error($e);
            return $this->fail([500,'处理上失败']);
        }

        return $this->success(true);
    }

    private function filter(Request $request, $builder): void
    {
        $request->validate([
            'filter.*.key' => 'required|in:' . implode(',', self::USER_FILTER_KEYS),
            'filter.*.condition' => 'required|in:>,<,=,>=,<=,模糊,!=',
            'filter.*.value' => 'required'
        ]);

        $filters = $request->input('filter');
        if (!$filters) {
            return;
        }

        foreach ($filters as $filter) {
            if ($filter['condition'] === '模糊') {
                $filter['condition'] = 'like';
                $filter['value'] = "%{$filter['value']}%";
            }
            if ($filter['key'] === 'd' || $filter['key'] === 'transfer_enable') {
                $filter['value'] = $filter['value'] * 1073741824;
            }
            if ($filter['key'] === 'invite_by_email') {
                $user = User::where('email', $filter['condition'], $filter['value'])->first();
                $inviteUserId = isset($user->id) ? $user->id : 0;
                $builder->where('invite_user_id', $inviteUserId);
                continue;
            }
            $builder->where($filter['key'], $filter['condition'], $filter['value']);
        }
    }

    private function getSortColumn($sort, string $default): string
    {
        return in_array($sort, self::USER_SORT_COLUMNS, true) ? $sort : $default;
    }
}
