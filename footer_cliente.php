    </div> <!-- Fecha portal-cliente-content -->
    
    <footer style="background: #f8fafc; padding: 20px; text-align: center; color: #64748b; border-top: 1px solid #e2e8f0; margin-top: 40px;">
        <p>&copy; <?php echo date('Y'); ?> INLAUDO - Conectando Saúde e Tecnologia</p>
        <p style="font-size: 0.85rem; margin-top: 5px;">
            Portal do Cliente v1.0 | 
            Sessão: <?php 
                $tempo_sessao = time() - $_SESSION['login_time'];
                $minutos = floor($tempo_sessao / 60);
                echo $minutos . ' min';
            ?>
        </p>
    </footer>
</body>
</html>
