<?php
namespace app\admin\service;

use app\admin\model\UserModel;
use app\common\service\BaseService;
use think\Exception;
use think\facade\Db;

/**
 * 用户管理-服务类
 * @author 陌上花开
 * @since 2022/7/3
 * Class UserService
 * @package app\admin\service
 */
class UserService extends BaseService
{
    /**
     * 构造函数
     * @author 陌上花开
     * @since 2022/7/3
     * UserService constructor.
     */
    public function __construct(){
        $this->model = new UserModel();
    }

    public function getAppUserList($appid){
        try{
            return $this->model->getAppUserList($appid);
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    public function list(){
        try{
            $result = $this->model->list();
            $rows = method_exists($result, 'items') ? $result->items() : iterator_to_array($result);
            $userIds = $this->collectColumnIds($rows, 'id');
            $appIds = $this->collectColumnIds($rows, 'appid');
            $powerIds = $this->collectColumnIds($rows, 'power');
            $appMap = empty($appIds) ? [] : Db::name('app')->whereIn('id', $appIds)->column('name', 'id');
            $powerMap = [];
            if (!empty($powerIds)) {
                foreach (Db::name('power_price')->whereIn('id', $powerIds)->select()->toArray() as $powerRow) {
                    $powerMap[intval($powerRow['id'])] = $powerRow;
                }
            }
            $cdkeyCounts = $this->countByUser('cdkey', $userIds);
            $userCounts = $this->countByUser('user', $userIds);
            $authCounts = $this->countByUser('auth', $userIds);
            $phoneIdentityIds = empty($userIds)
                ? []
                : array_fill_keys(array_map('intval', Db::name('user_phone_identity')->whereIn('user_id', $userIds)->column('user_id')), true);
            foreach($result as $res){
                $res['powerSpan'] = 'gray';
                $res['powerName'] = t('user.power_error');
                $res['appName'] = t('app.not_exist');
                $powerInfo = $powerMap[intval($res['power'])] ?? null;
                if($powerInfo) {
                    if ($powerInfo['default_power'] == 0) {
                        if ($powerInfo['parentid'] == 0) {
                            $res['powerSpan'] = 'red';
                        } else {
                            $res['powerSpan'] = 'blue';
                        }
                    } else {
                        $res['powerSpan'] = 'gray';
                    }
                    $res['powerName'] = $powerInfo['name'];
                }
                if(isset($appMap[intval($res['appid'])])) {
                    $res['appName'] = $appMap[intval($res['appid'])];
                }
                if(!empty($res['ip'])){
                    $ipList = @unserialize((string)$res['ip'], ['allowed_classes' => false]);
                    $res['ip'] = is_array($ipList) ? implode('|', $ipList) : (string)$res['ip'];
                }
                $userId = intval($res['id']);
                $res['cdkeyCount'] = $cdkeyCounts[$userId] ?? 0;
                $res['userCount'] = $userCounts[$userId] ?? 0;
                $res['authCount'] = $authCounts[$userId] ?? 0;
                $res['phone'] = \app\common\service\PhoneVerificationService::normalizePhone($res['phone'] ?? '');
                $res['phone_verified'] = ($res['phone'] !== ''
                    && !empty($res['phone_verified_at'])
                    && isset($phoneIdentityIds[$userId])) ? 1 : 0;
            }
            return $result;
        }catch (\Exception $e){
            throw new Exception($e->getMessage());
        }
    }

    private function countByUser(string $table, array $userIds): array
    {
        if (empty($userIds)) {
            return [];
        }
        $rows = Db::name($table)
            ->whereIn('userid', $userIds)
            ->field('userid, COUNT(*) AS total')
            ->group('userid')
            ->select()
            ->toArray();
        $map = [];
        foreach ($rows as $row) {
            $map[intval($row['userid'])] = intval($row['total']);
        }
        return $map;
    }

    private function collectColumnIds(array $rows, string $field): array
    {
        $ids = [];
        foreach ($rows as $row) {
            $value = is_array($row) ? ($row[$field] ?? 0) : ($row[$field] ?? 0);
            $value = intval($value);
            if ($value > 0) {
                $ids[] = $value;
            }
        }
        return array_values(array_unique($ids));
    }
}
