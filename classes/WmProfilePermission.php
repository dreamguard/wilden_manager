<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmProfilePermission
{
    public static function isSuperAdmin($idProfile)
    {
        $superAdminProfile = self::getSuperAdminProfileId();

        return $superAdminProfile > 0 && (int) $idProfile === $superAdminProfile;
    }

    public static function getSuperAdminProfileId()
    {
        if (defined('_PS_ADMIN_PROFILE_')) {
            return (int) _PS_ADMIN_PROFILE_;
        }

        return (int) Configuration::get('PS_ADMIN_PROFILE');
    }

    public static function canExport($idProfile)
    {
        return self::hasPermission((int) $idProfile, 'can_export');
    }

    public static function canViewDiagnostics($idProfile)
    {
        return self::hasPermission((int) $idProfile, 'can_view_diagnostics');
    }

    public static function getProfiles($idLang)
    {
        $stored = array();
        $rows = Db::getInstance()->executeS(
            'SELECT `id_profile`, `can_export`, `can_view_diagnostics`
             FROM `' . _DB_PREFIX_ . 'wilden_manager_profile_permission`'
        );
        foreach ((array) $rows as $row) {
            $stored[(int) $row['id_profile']] = $row;
        }

        $profiles = array();
        foreach ((array) Profile::getProfiles((int) $idLang) as $profile) {
            $idProfile = (int) $profile['id_profile'];
            $isSuperAdmin = self::isSuperAdmin($idProfile);
            $profiles[] = array(
                'id_profile' => $idProfile,
                'name' => (string) $profile['name'],
                'can_export' => $isSuperAdmin || (!empty($stored[$idProfile]['can_export'])),
                'can_view_diagnostics' => $isSuperAdmin || (!empty($stored[$idProfile]['can_view_diagnostics'])),
                'is_super_admin' => $isSuperAdmin,
            );
        }

        return $profiles;
    }

    public static function save(array $exportProfiles, array $diagnosticProfiles, $idLang)
    {
        $exportProfiles = array_map('intval', $exportProfiles);
        $diagnosticProfiles = array_map('intval', $diagnosticProfiles);
        $now = date('Y-m-d H:i:s');

        foreach ((array) Profile::getProfiles((int) $idLang) as $profile) {
            $idProfile = (int) $profile['id_profile'];
            $isSuperAdmin = self::isSuperAdmin($idProfile);
            $data = array(
                'can_export' => (int) ($isSuperAdmin || in_array($idProfile, $exportProfiles, true)),
                'can_view_diagnostics' => (int) ($isSuperAdmin || in_array($idProfile, $diagnosticProfiles, true)),
                'date_upd' => $now,
            );
            $exists = Db::getInstance()->getValue(
                'SELECT `id_profile` FROM `' . _DB_PREFIX_ . 'wilden_manager_profile_permission`
                 WHERE `id_profile` = ' . $idProfile,
                false
            );
            if ($exists) {
                $saved = Db::getInstance()->update(
                    'wilden_manager_profile_permission',
                    $data,
                    '`id_profile` = ' . $idProfile,
                    0,
                    false,
                    false
                );
            } else {
                $data['id_profile'] = $idProfile;
                $data['date_add'] = $now;
                $saved = Db::getInstance()->insert(
                    'wilden_manager_profile_permission',
                    $data,
                    false,
                    false
                );
            }
            if (!$saved) {
                return false;
            }
        }

        return true;
    }

    public static function installDefaults($idLang)
    {
        return self::save(array(), array(), (int) $idLang);
    }

    public static function ensureSuperAdmin()
    {
        $idProfile = self::getSuperAdminProfileId();
        if ($idProfile <= 0) {
            return false;
        }

        $now = date('Y-m-d H:i:s');
        $data = array(
            'can_export' => 1,
            'can_view_diagnostics' => 1,
            'date_upd' => $now,
        );
        $exists = Db::getInstance()->getValue(
            'SELECT `id_profile` FROM `' . _DB_PREFIX_ . 'wilden_manager_profile_permission`
             WHERE `id_profile` = ' . $idProfile,
            false
        );

        if ($exists) {
            return Db::getInstance()->update(
                'wilden_manager_profile_permission',
                $data,
                '`id_profile` = ' . $idProfile,
                0,
                false,
                false
            );
        }

        $data['id_profile'] = $idProfile;
        $data['date_add'] = $now;

        return Db::getInstance()->insert(
            'wilden_manager_profile_permission',
            $data,
            false,
            false
        );
    }

    private static function hasPermission($idProfile, $field)
    {
        if (self::isSuperAdmin($idProfile)) {
            return true;
        }
        if ($idProfile <= 0 || !in_array($field, array('can_export', 'can_view_diagnostics'), true)) {
            return false;
        }

        return (bool) Db::getInstance()->getValue(
            'SELECT `' . bqSQL($field) . '`
             FROM `' . _DB_PREFIX_ . 'wilden_manager_profile_permission`
             WHERE `id_profile` = ' . (int) $idProfile,
            false
        );
    }
}
