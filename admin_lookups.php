<?php
require_once __DIR__ . '/config.php';

$pageTitle = 'Admin Districts & Circuits';
$pdo = db();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'add_district') {
        $name = trim($_POST['district_name'] ?? '');
        if ($name === '') {
            flash('District name is required.', 'error');
        } else {
            $stmt = $pdo->prepare('INSERT IGNORE INTO districts (name) VALUES (?)');
            $stmt->execute([$name]);
            flash('District added.');
        }
        redirect('admin_lookups.php');
    }

    if ($action === 'add_circuit') {
        $districtId = (int) ($_POST['district_id'] ?? 0);
        $name = trim($_POST['circuit_name'] ?? '');
        if ($districtId < 1 || $name === '') {
            flash('District and circuit name are required.', 'error');
        } else {
            $stmt = $pdo->prepare('INSERT IGNORE INTO circuits (district_id, name) VALUES (?, ?)');
            $stmt->execute([$districtId, $name]);
            flash('Circuit added.');
        }
        redirect('admin_lookups.php');
    }
}

$districts = $pdo->query('SELECT id, name FROM districts ORDER BY name')->fetchAll();
$circuits = $pdo->query(
    'SELECT c.id, c.name, d.name AS district
     FROM circuits c
     JOIN districts d ON d.id = c.district_id
     ORDER BY d.name, c.name'
)->fetchAll();

require __DIR__ . '/includes/header.php';
?>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Admin</p>
            <h2>District & Circuit Management</h2>
        </div>
    </div>

    <div class="grid two">
        <form method="post" class="form">
            <input type="hidden" name="action" value="add_district">
            <label>
                New district
                <input name="district_name" required>
            </label>
            <button class="button" type="submit">Add district</button>
        </form>

        <form method="post" class="form">
            <input type="hidden" name="action" value="add_circuit">
            <label>
                District
                <select name="district_id" required>
                    <option value="">Select district</option>
                    <?php foreach ($districts as $district): ?>
                        <option value="<?= (int) $district['id'] ?>"><?= e($district['name']) ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>
                New circuit
                <input name="circuit_name" required>
            </label>
            <button class="button" type="submit">Add circuit</button>
        </form>
    </div>
</section>

<section class="panel">
    <div class="panel-heading">
        <div>
            <p class="eyebrow">Current</p>
            <h2>Circuits</h2>
        </div>
    </div>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>District</th>
                    <th>Circuit</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($circuits as $circuit): ?>
                    <tr>
                        <td><?= e($circuit['district']) ?></td>
                        <td><?= e($circuit['name']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/includes/footer.php'; ?>
