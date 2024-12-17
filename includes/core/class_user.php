<?php

class User {

    // GENERAL

    public static function user_info($d) {
        // vars
        $user_id = is_numeric($d) ? (int)$d : 0;
        $phone = isset($d['phone']) ? preg_replace('~\D+~', '', $d['phone']) : 0;
        // where
        if ($user_id) $where = "user_id='".$user_id."'";
        else if ($phone) $where = "phone='".$phone."'";
        else return [
            'id' => 0,
            'first_name' => null,
            'last_name' => null,
            'phone' => null,
            'email' => null,
            'plot_id' => null,
            'access' => 0
        ];
        // info
        $q = DB::query("SELECT user_id, first_name, last_name, phone, email, plot_id, access FROM users WHERE ".$where." LIMIT 1;") or die (DB::error());
        if ($row = DB::fetch_row($q)) {
            return [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'plot_id' => $row['plot_id'],
                'access' => (int) $row['access']
            ];
        } else {
            return [
                'id' => 0,
                'first_name' => null,
                'last_name' => null,
                'phone' => null,
                'email' => null,
                'plot_id' => null,
                'access' => 0
            ];
        }
    }

    public static function users_list_plots($number) {
        // vars
        $items = [];
        // info
        $q = DB::query("SELECT user_id, plot_id, first_name, email, phone
            FROM users WHERE plot_id LIKE '%".$number."%' ORDER BY user_id;") or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $plot_ids = explode(',', $row['plot_id']);
            $val = false;
            foreach($plot_ids as $plot_id) if ($plot_id == $number) $val = true;
            if ($val) $items[] = [
                'id' => (int) $row['user_id'],
                'first_name' => $row['first_name'],
                'email' => $row['email'],
                'phone_str' => phone_formatting($row['phone'])
            ];
        }
        // output
        return $items;
    }

    public static function users_list($d = [])
    {
        // vars
        $search = isset($d['search']) && trim($d['search']) ? $d['search'] : '';
        $offset = isset($d['offset']) && is_numeric($d['offset']) ? $d['offset'] : 0;
        $limit = 20;
        $items = [];
        // where
        $where = [];
        if ($search) $where[] = "phone LIKE '%" . $search . "%'" .
            " OR first_name LIKE '%". $search . "%'" .
            " OR email LIKE '%" . $search . "%'";
        $where = $where ? 'WHERE ' . implode(' AND ', $where) : '';
        // info
        $q = DB::query('SELECT user_id, plot_id, first_name, last_name, phone, email, last_login
            FROM users ' . $where . ' ORDER BY user_id+0 LIMIT ' . $offset . ', ' . $limit . ';') or die (DB::error());
        while ($row = DB::fetch_row($q)) {
            $items[] = [
                'id' => (int)$row['user_id'],
                'plot_id' => $row['plot_id'],
                'first_name' => $row['first_name'],
                'last_name' => $row['last_name'],
                'phone' => $row['phone'],
                'email' => $row['email'],
                'last_login' => date('Y/m/d', $row['last_login'])
            ];
        }
        // paginator
        $q = DB::query('SELECT count(*) FROM users ' . $where . ';');
        $count = ($row = DB::fetch_row($q)) ? $row['count(*)'] : 0;
        $url = 'users?';
        if ($search) $url .= '&search=' . $search;
        paginator($count, $offset, $limit, $url, $paginator);
        // output
        return ['items' => $items, 'paginator' => $paginator];
    }

    public static function users_fetch($d = [])
    {
        $info = User::users_list($d);
        HTML::assign('users', $info['items']);
        return ['html' => HTML::fetch('./partials/users_table.html'), 'paginator' => $info['paginator']];
    }

    // ACTIONS

    public static function user_edit_window($d = [])
    {
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        HTML::assign('user', user::user_info($user_id));
        return ['html' => HTML::fetch('./partials/user_edit.html')];
    }

    public static function user_edit_update($d = [])
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : 0;
        $first_name = isset($d['first_name']) && trim($d['first_name'])
            ? trim($d['first_name'])
            : die('invalid \'first_name\' field value');
        $last_name = isset($d['last_name']) && trim($d['last_name'])
            ? trim($d['last_name'])
            : die('invalid \'last_name\' field value');
        $phone = isset($d['phone'])
        && is_numeric($d['phone'])
        && ($phone = filter_var($d['phone'], FILTER_VALIDATE_INT)) !== false
            ? $phone
            : die('invalid \'phone\' field value');
        $email = isset($d['email']) && trim($d['email'])
            ? strtolower(trim($d['email']))
            : die('invalid \'email\' field value');
        $plot_id = isset($d['plot_id']) && trim($d['plot_id']) ? trim($d['plot_id']) : '';
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // update
        if ($user_id) {
            $set = [];
            $set[] = "first_name='" . $first_name . "'";
            $set[] = "last_name='" . $last_name . "'";
            $set[] = "phone='" . $phone . "'";
            $set[] = "email='" . $email . "'";
            $set[] = "plot_id='" . $plot_id . "'";
            $set[] = "updated='" . Session::$ts . "'";
            $set = implode(', ', $set);
            DB::query('UPDATE users SET ' . $set . " WHERE user_id='" . $user_id . "' LIMIT 1;") or die (DB::error());
        } else {
            DB::query("INSERT INTO users (
                first_name,
                last_name,
                phone,
                email,
                plot_id,
                updated
            ) VALUES (
                '" . $first_name . "',
                '" . $last_name . "',
                '" . $phone . "',
                '" . $email . "',
                '" . $plot_id . "',
                '" . Session::$ts . "'
            );") or die (DB::error());
        }
        // output
        return user::users_fetch(['offset' => $offset]);
    }

    public static function delete($d)
    {
        // vars
        $user_id = isset($d['user_id']) && is_numeric($d['user_id']) ? $d['user_id'] : die('invalid \'user_id\' field value');
        $offset = isset($d['offset']) ? preg_replace('~\D+~', '', $d['offset']) : 0;
        // delete
        DB::query('DELETE FROM users WHERE user_id = ' . $user_id) or die (DB::error());
        // output
        return user::users_fetch(['offset' => $offset]);
    }
}
