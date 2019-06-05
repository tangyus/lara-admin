<?php

namespace App\Exports;

use App\Model\UserPrize;
use Maatwebsite\Excel\Concerns\FromArray;

class UserPrizeExport implements FromArray
{
    protected $sId;

    public function __construct($shopId = 0)
    {
        if ($shopId) {
            $this->sId = $shopId;
        }
    }

    public function array(): array
    {
        $data[] = ['昵称', '电话', '奖品名称', '奖品类型', '中奖时间', '兑换时间'];
        UserPrize::with(['prize', 'user'])
            ->where(function ($query) {
                $query->where('up_received', 1);
                if ($this->sId) {
                    $query->where('up_shop_id', $this->sId);
                }
            })
            ->get()
            ->map(function ($item) use (&$data) {
                $data[] = [
                    !empty($item->user->u_nick) ? $item->user->u_nick : '测试',
                    $item->user ? $item->user->u_phone : '',
                    $item->prize->p_type,
                    $item->prize->p_name,
                    $item->up_updated->format('Y-m-d H:i:s'), // diffForHumans
                    $item->up_created->format('Y-m-d H:i:s'), // diffForHumans
                ];
            });
        return $data;
    }
}