    </main>
    
    <!-- ============================================================================
         FOOTER
         ============================================================================ -->
    
    <footer style="background-color: #1f2937; color: white; padding: 2rem 1rem; margin-top: 3rem; text-align: center; border-top: 1px solid #e2e8f0;">
        <div class="container-fluid">
            <div class="row mb-3">
                <div class="col-md-4 mb-3 mb-md-0">
                    <h6 style="font-weight: 700; margin-bottom: 1rem;">ERP INLAUDO</h6>
                    <p style="font-size: 0.9rem; color: #d1d5db; margin: 0;">
                        Sistema de Gestão Empresarial Profissional
                    </p>
                </div>
                <div class="col-md-4 mb-3 mb-md-0">
                    <h6 style="font-weight: 700; margin-bottom: 1rem;">Links Rápidos</h6>
                    <ul style="list-style: none; padding: 0; margin: 0;">
                        <li><a href="index.php" style="color: #d1d5db; text-decoration: none; font-size: 0.9rem;">Dashboard</a></li>
                        <li><a href="clientes.php" style="color: #d1d5db; text-decoration: none; font-size: 0.9rem;">Clientes</a></li>
                        <li><a href="contas_receber.php" style="color: #d1d5db; text-decoration: none; font-size: 0.9rem;">Financeiro</a></li>
                    </ul>
                </div>
                <div class="col-md-4">
                    <h6 style="font-weight: 700; margin-bottom: 1rem;">Suporte</h6>
                    <p style="font-size: 0.9rem; color: #d1d5db; margin: 0;">
                        <i class="fas fa-envelope"></i> suporte@inlaudo.com.br<br>
                        <i class="fas fa-phone"></i> (11) 3000-0000
                    </p>
                </div>
            </div>
            
            <hr style="border-color: #374151; margin: 1.5rem 0;">
            
            <div style="font-size: 0.85rem; color: #9ca3af;">
                <p style="margin: 0.5rem 0;">
                    &copy; <?php echo date('Y'); ?> ERP INLAUDO. Todos os direitos reservados.
                </p>
                <p style="margin: 0.5rem 0;">
                    Versão 5.0.0 | Desenvolvido com <i class="fas fa-heart" style="color: #ef4444;"></i> por Dev Senior
                </p>
            </div>
        </div>
    </footer>
    
    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Dashboard JS -->
    <script src="assets/js/dashboard.js"></script>
    
    <script>
        // Inicializar tooltips Bootstrap
        document.addEventListener('DOMContentLoaded', function() {
            const tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));
            const tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl);
            });
        });
    </script>
</body>
</html>
