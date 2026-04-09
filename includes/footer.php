    </main>

    <!-- Logout Confirmation Modal -->
    <div class="modal-overlay" id="logoutModal">
        <div class="modal">
            <h2 class="modal-title">Confirm Logout</h2>
            <p class="modal-text">Are you sure you want to end your session and return to the login screen?</p>
            <div class="modal-actions">
                <button class="btn-cancel" onclick="hideLogoutModal()">Cancel</button>
                <a href="../../logout.php" class="btn-confirm">Logout</a>
            </div>
        </div>
    </div>

    <script>
        function showLogoutModal() {
            document.getElementById('logoutModal').classList.add('active');
        }

        function hideLogoutModal() {
            document.getElementById('logoutModal').classList.remove('active');
        }
    </script>
</body>
</html>
