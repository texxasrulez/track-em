<?php
use TrackEm\Core\Security;
use TrackEm\Core\Theme;
use TrackEm\Core\I18n;
?>
<style>
  .form input[type="text"],
  .form input[type="password"],
  .form select {
    background-color: var(--bg);
    width: 180px;         /* Adjust this to your liking */
    display: inline-block;
    margin-right: 6px;
  }

  .form-inline input[type="text"],
  .form-inline input[type="password"],
  .form-inline select {
    background-color: var(--bg);
    width: 140px;
  }

  .form button {
    padding: 4px 10px;
  }

  table {
    width: auto;
  }

  table td, table th {
    padding: 6px 8px;
  }
  .card table {
  width: 100%;
}
.card td form {
  display: inline-flex;
  align-items: center;
  gap: 5px;
}
</style>
<div class="card">
  <h3>Users</h3>

  <form class="form form-row" method="POST" action="?p=admin.users" style="margin-bottom:12px">
	<input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
	<input type="hidden" name="action" value="create"/>
    <input type="text" name="username" placeholder="Username" required />
    <select name="role">
      <option value="admin"><?= I18n::t('admin','admin') ?></option>
      <option value="user" selected><?= I18n::t('user','user') ?></option>
    </select>
    <input type="password" name="password" placeholder="Password (optional)" />
    <button type="submit" class="button btn"><?= I18n::t('add_user','Add User') ?></button>
  </form>

  <table>
    <thead>
      <tr><th><?= I18n::t('id','ID') ?></th><th><?= I18n::t('username','Username') ?></th><th><?= I18n::t('role','Role') ?></th><th><?= I18n::t('created','Created') ?></th><th><?= I18n::t('actions','Actions') ?></th></tr>
    </thead>
    <tbody>
      <?php foreach ($users as $u): ?>
      <tr>
        <td><?= (int)$u['id'] ?></td>
        <td>
          <form method="POST" action="?p=admin.users" class="form form-inline">
            <input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
			<input type="hidden" name="action" value="update"/>
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>"/>
            <input type="text" name="username" value="<?= htmlspecialchars($u['username']) ?>" required />
            <select name="role">
              <option value="admin" <?= $u['role']==='admin'?'selected':'' ?>><?= I18n::t('admin','admin') ?></option>
              <option value="user"  <?= $u['role']!=='admin'?'selected':'' ?>><?= I18n::t('user','user') ?></option>
            </select>
            <input type="password" name="password" placeholder="New password (leave blank to keep)"/>
            <button type="submit" class="button btn"><?= I18n::t('save','Save') ?></button>
          </form>
        </td>
        <td><?= htmlspecialchars($u['role']) ?></td>
        <td><?= htmlspecialchars($u['created_at']) ?></td>
        <td>
          <form method="POST" action="?p=admin.users" onsubmit="return confirm('Delete user <?= htmlspecialchars($u['username']) ?>
			<input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
			<input type="hidden" name="csrf" value="<?= Security::csrfToken() ?>"/>
            <input type="hidden" name="action" value="delete"/>
            <input type="hidden" name="id" value="<?= (int)$u['id'] ?>"/>
            <button type="submit" class="button danger"><?= I18n::t('delete','Delete') ?></button>
          </form>
        </td>
      </tr>
      <?php endforeach; ?>
    </tbody>
  </table>
</div>
