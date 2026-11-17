<?php

if (!defined('_PS_VERSION_')) {
    exit;
}

class WmProfilePermission
{
    /**
     * Module permissions stored per employee profile. SuperAdmin always has all of them.
     */
    const FIELDS = array('can_export', 'can_view_diagnostics', 'can_edit_history');

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

    public static function canEditHistory($idProfile)
    {
        return self::hasPermission((int) $idProfile, 'can_edit_history');
    }

    public static function getProfiles($idLang)
    {
        $stored = array();
        $rows = Db::getInstance()->executeS(
            'SELECT `id_profile`, `' . implode('`, `', self::FIELDS) . '`
             FROM `' . _DB_PREFIX_ . 'wildenmanager_profile_permission`'
        );
        foreach ((array) $rows as $row) {
            $stored[(int) $row['id_profile']] = $row;
        }

        $profiles = array();
        foreach ((array) Profile::getProfiles((int) $idLang) as $profile) {
            $idProfile = (int) $profile['id_profile'];
            $isSuperAdmin = self::isSuperAdmin($idProfile);
            $entry = array(
                'id_profile' => $idProfile,
                'name' => (string) $profile['name'],
                'is_super_admin' => $isSuperAdmin,
            );
            foreach (self::FIELDS as $field) {
                $entry[$field] = $isSuperAdmin || !empty($stored[$idProfile][$field]);
            }
            $profiles[] = $entry;
        }

        return $profiles;
    }

    /**
     * @param array $profilesByField field => list of profile IDs that receive the permission
     */
    public static function save(array $profilesByField, $idLang)
    {
        $now = date('Y-m-d H:i:s');

        foreach ((array) Profile::getProfiles((int) $idLang) as $profile) {
            $idProfile = (int) $profile['id_profile'];
            $isSuperAdmin = self::isSuperAdmin($idProfile);
            $data = array('date_upd' => $now);
            foreach (self::FIELDS as $field) {
                $granted = isset($profilesByField[$field])
                    ? array_map('intval', (array) $profilesByField[$field])
                    : array();
                $data[$field] = (int) ($isSuperAdmin || in_array($idProfile, $granted, true));
            }
            if (!self::write($idProfile, $data, $now)) {
                return false;
            }
        }

        return true;
    }

    public static function installDefaults($idLang)
    {
        return self::save(array(), (int) $idLang);
    }

    public static function ensureSuperAdmin()
    {
        $idProfile = self::getSuperAdminProfileId();
        if ($idProfile <= 0) {
            return false;
        }
        $now = date('Y-m-d H:i:s');
        $data = array('date_upd' => $now);
        foreach (self::FIELDS as $field) {
            $data[$field] = 1;
        }

        return self::write($idProfile, $data, $now);
    }

    private static function write($idProfile, array $data, $now)
    {
        $exists = WmDb::exists(
            'SELECT 1 FROM `' . _DB_PREFIX_ . 'wildenmanager_profile_permission`
             WHERE `id_profile` = ' . (int) $idProfile
        );
        if ($exists) {
            return Db::getInstance()->update(
                'wildenmanager_profile_permission',
                $data,
                '`id_profile` = ' . (int) $idProfile,
                0,
                false,
                false
            );
        }
        $data['id_profile'] = (int) $idProfile;
        $data['date_add'] = $now;

        return Db::getInstance()->insert('wildenmanager_profile_permission', $data, false, false);
    }

    private static function hasPermission($idProfile, $field)
    {
        if (self::isSuperAdmin($idProfile)) {
            return true;
        }
        if ($idProfile <= 0 || !in_array($field, self::FIELDS, true)) {
            return false;
        }

        return (bool) WmDb::fetchValue(
            'SELECT `' . bqSQL($field) . '`
             FROM `' . _DB_PREFIX_ . 'wildenmanager_profile_permission`
             WHERE `id_profile` = ' . (int) $idProfile
        );
    }
}
