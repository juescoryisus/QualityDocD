// QualityDoc — Scripts de sitio

document.addEventListener('DOMContentLoaded', () => {

    // Auto-cerrar alertas tras 5 segundos
    document.querySelectorAll('.alert.alert-dismissible').forEach(alert => {
        setTimeout(() => {
            const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
            bsAlert.close();
        }, 5000);
    });

    // Vista previa de etiquetas al escribir
    const tagsInput = document.querySelector('input[name="Tags"]');
    if (tagsInput) {
        const preview = document.createElement('div');
        preview.className = 'd-flex flex-wrap gap-1 mt-1';
        tagsInput.parentNode.appendChild(preview);

        const render = () => {
            preview.innerHTML = tagsInput.value
                .split(',')
                .map(t => t.trim())
                .filter(Boolean)
                .map(t => `<span class="badge bg-light text-secondary border">${t}</span>`)
                .join('');
        };

        tagsInput.addEventListener('input', render);
        render();
    }
});