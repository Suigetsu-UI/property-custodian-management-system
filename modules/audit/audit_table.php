<table class="asset-table" id="auditTable">

    <thead>

        <tr>

            <th>Audit ID</th>
            <th>Asset</th>
            <th>Auditor</th>
            <th>Date</th>
            <th>Status</th>
            <th>Result</th>
            <th>Actions</th>

        </tr>

    </thead>

    <tbody>

        <?php

        $audits = $_SESSION['filtered_audits']
            ?? $_SESSION['audits']
            ?? [];

        unset($_SESSION['filtered_audits']);

        foreach ($audits as $index => $item):

        ?>

        <tr class="audit-row">

            <td><?= htmlspecialchars($item['audit_id']); ?></td>
            <td><?= htmlspecialchars($item['asset_name']); ?></td>
            <td><?= htmlspecialchars($item['auditor']); ?></td>
            <td><?= htmlspecialchars($item['audit_date']); ?></td>
            <td><?= htmlspecialchars($item['status']); ?></td>
            <td><?= htmlspecialchars($item['result']); ?></td>
            <td>

                <a href="view_audit.php?id=<?= $index ?>" class="btn btn-primary">View</a>
                <a href="edit_audit.php?id=<?= $index ?>" class="btn btn-warning">Edit</a>
                <a href="delete_audit.php?id=<?= $index ?>" class="btn btn-danger" onclick="return confirm('Delete this audit record?');">Delete</a>

            </td>

        </tr>

        <?php endforeach; ?>

        <?php if (empty($audits)): ?>

        <tr>

            <td colspan="7" style="text-align:center; padding:40px;">

                No audit records found.

            </td>

        </tr>

        <?php endif; ?>

    </tbody>

</table>