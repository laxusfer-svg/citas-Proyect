document.addEventListener("DOMContentLoaded", function () {
    inicializarMostrarPassword();
    inicializarGraficosPanel();
    inicializarGraficosReporte();
    inicializarFiltroCitas();
    inicializarReserva();
});

function inicializarMostrarPassword() {
    const password = document.getElementById("password");
    const boton = document.getElementById("botonPassword");

    if (!password || !boton) return;

    boton.addEventListener("click", function () {
        const mostrar = password.type === "password";
        password.type = mostrar ? "text" : "password";
        boton.textContent = mostrar ? "🙈" : "👁";
        boton.setAttribute("aria-label", mostrar ? "Ocultar contraseña" : "Mostrar contraseña");
    });
}

function inicializarGraficosPanel() {
    const datosElemento = document.getElementById("panel-chart-data");

    if (!datosElemento || !window.Chart) return;

    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue("--color-primary").trim() || "#1976e8";
    const datos = JSON.parse(datosElemento.textContent);
    const estadosCanvas = document.getElementById("graficoEstados");
    const especialidadesCanvas = document.getElementById("graficoEspecialidades");

    if (estadosCanvas) {
        new Chart(estadosCanvas, {
            type: "doughnut",
            data: {
                labels: ["Atendidas", "Pendientes", "Confirmadas", "Canceladas"],
                datasets: [{
                    data: [datos.atendidas, datos.pendientes, datos.confirmadas, datos.canceladas],
                    backgroundColor: ["#10ad7b", "#f2a900", primaryColor, "#ed3d48"],
                    borderWidth: 3,
                    borderColor: "#ffffff"
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: "65%",
                plugins: { legend: { position: "bottom" } }
            }
        });
    }

    if (especialidadesCanvas) {
        new Chart(especialidadesCanvas, {
            type: "bar",
            data: {
                labels: datos.especialidades.map(function (item) { return item.especialidad; }),
                datasets: [{
                    label: "Citas",
                    data: datos.especialidades.map(function (item) { return item.total; }),
                    backgroundColor: primaryColor,
                    borderRadius: 7
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { display: false } }
            }
        });
    }
}

function inicializarGraficosReporte() {
    const datosElemento = document.getElementById("report-chart-data");

    if (!datosElemento || !window.Chart) return;

    const primaryColor = getComputedStyle(document.documentElement).getPropertyValue("--color-primary").trim() || "#1976e8";
    const datos = JSON.parse(datosElemento.textContent);
    const estadosCanvas = document.getElementById("chartEstados");
    const especialidadesCanvas = document.getElementById("chartEspecialidades");
    const mesesCanvas = document.getElementById("chartMes");

    if (estadosCanvas) {
        new Chart(estadosCanvas, {
            type: "doughnut",
            data: {
                labels: ["Atendidas", "Pendientes", "Confirmadas", "Canceladas"],
                datasets: [{
                    data: [
                        datos.estados.atendida || 0,
                        datos.estados.pendiente || 0,
                        datos.estados.confirmada || 0,
                        datos.estados.cancelada || 0
                    ],
                    backgroundColor: ["#28a745", "#d68910", primaryColor, "#dc3545"]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { position: "bottom" } }
            }
        });
    }

    if (especialidadesCanvas) {
        new Chart(especialidadesCanvas, {
            type: "bar",
            data: {
                labels: datos.especialidades.map(function (item) { return item.especialidad; }),
                datasets: [{
                    label: "Total Citas",
                    data: datos.especialidades.map(function (item) { return item.total; }),
                    backgroundColor: primaryColor,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { display: false } }
            }
        });
    }

    if (mesesCanvas) {
        new Chart(mesesCanvas, {
            type: "bar",
            data: {
                labels: datos.meses.map(function (item) { return item.mes; }),
                datasets: [
                    { label: "Atendidas", data: datos.meses.map(function (item) { return item.atendidas; }), backgroundColor: "#28a745" },
                    { label: "Pendientes", data: datos.meses.map(function (item) { return item.pendientes; }), backgroundColor: "#d68910" },
                    { label: "Confirmadas", data: datos.meses.map(function (item) { return item.confirmadas; }), backgroundColor: primaryColor },
                    { label: "Canceladas", data: datos.meses.map(function (item) { return item.canceladas; }), backgroundColor: "#dc3545" }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: { y: { beginAtZero: true, ticks: { precision: 0 } } },
                plugins: { legend: { position: "bottom" } }
            }
        });
    }
}

function inicializarFiltroCitas() {
    const tarjetas = document.querySelectorAll(".tarjeta-filtro");
    const filas = document.querySelectorAll(".fila-cita");
    const mensajes = document.querySelectorAll(".fila-sin-resultados");
    const textos = document.querySelectorAll(".textoFiltro");
    const cantidades = document.querySelectorAll(".cantidadFiltro");

    if (!tarjetas.length) return;

    tarjetas.forEach(function (tarjeta) {
        tarjeta.addEventListener("click", function () {
            const filtro = this.getAttribute("data-filtro");
            let cantidad = 0;

            tarjetas.forEach(function (item) {
                item.classList.remove("activo");
            });
            this.classList.add("activo");

            filas.forEach(function (fila) {
                const estado = fila.getAttribute("data-estado");
                const mostrar = filtro === "todos" || estado === filtro;
                fila.classList.toggle("fila-oculta", !mostrar);
                if (mostrar) cantidad++;
            });

            mensajes.forEach(function (mensaje) {
                mensaje.classList.toggle("visible", cantidad === 0);
            });

            let texto = "Todas las últimas 12";
            if (filtro === "atendida") texto = "Citas atendidas";
            if (filtro === "pendiente") texto = "Citas pendientes";
            if (filtro === "confirmada") texto = "Citas confirmadas";
            if (filtro === "cancelada") texto = "Citas canceladas";

            textos.forEach(function (elemento) {
                elemento.textContent = texto;
            });
            cantidades.forEach(function (elemento) {
                elemento.textContent = cantidad;
            });
        });
    });
}

function inicializarReserva() {
    const especialidadSelect = document.getElementById("especialidad_id");
    const doctorSelect = document.getElementById("doctor_id");
    const calendario = document.getElementById("calendarioVisor");

    if (!especialidadSelect || !doctorSelect) return;

    const opcionesDoctor = Array.from(doctorSelect.querySelectorAll("option[data-especialidad]"));

    const fechaInput = document.getElementById("fecha");
    const listaHorarios = document.getElementById("listaHorarios");
    const mensajeHorarios = document.getElementById("mensajeHorarios");
    const horaInput = document.getElementById("hora");
    const btnGuardar = document.getElementById("btnGuardar");
    const infoDoctor = document.getElementById("infoDoctor");
    const especialidadDoctor = document.getElementById("especialidadDoctor");
    const horarioDoctor = document.getElementById("horarioDoctor");
    const refrigerioDoctor = document.getElementById("refrigerioDoctor");
    const enlaceCerrar = document.querySelector(".modal-cerrar");

    document.addEventListener("keydown", function (event) {
        if (event.key === "Escape" && enlaceCerrar) {
            window.location.href = enlaceCerrar.href;
        }
    });

    if (calendario && window.flatpickr) {
        window.flatpickr(calendario, {
            inline: true,
            locale: "es",
            minDate: "today",
            dateFormat: "Y-m-d",
            onChange: function (selectedDates, dateStr) {
                fechaInput.value = dateStr;
                horaInput.value = "";
                btnGuardar.disabled = true;
                cargarHorarios();
            }
        });
    }

    doctorSelect.addEventListener("change", function () {
        const opcion = this.options[this.selectedIndex];

        horaInput.value = "";
        btnGuardar.disabled = true;
        listaHorarios.innerHTML = "";

        if (this.value !== "") {
            especialidadDoctor.textContent = opcion.dataset.especialidad;
            horarioDoctor.textContent = opcion.dataset.inicio.substring(0, 5) + " - " + opcion.dataset.fin.substring(0, 5);

            if (opcion.dataset.refrigerioInicio && opcion.dataset.refrigerioFin) {
                refrigerioDoctor.textContent = opcion.dataset.refrigerioInicio.substring(0, 5) + " - " + opcion.dataset.refrigerioFin.substring(0, 5);
            } else {
                refrigerioDoctor.textContent = "Sin refrigerio registrado";
            }

            infoDoctor.classList.add("visible");
        } else {
            infoDoctor.classList.remove("visible");
        }

        cargarHorarios();
    });

    function filtrarDoctores(limpiarSeleccion) {
        const especialidad = especialidadSelect.value;
        doctorSelect.disabled = especialidad === "";

        opcionesDoctor.forEach(function (opcion) {
            const coincide = especialidad !== "" && opcion.dataset.especialidad === especialidad;
            opcion.hidden = !coincide;

            if (!coincide && opcion.selected) {
                doctorSelect.value = "";
            }
        });

        if (limpiarSeleccion) {
            doctorSelect.value = "";
        }

        doctorSelect.dispatchEvent(new Event("change"));
    }

    especialidadSelect.addEventListener("change", function () {
        filtrarDoctores(true);
    });

    filtrarDoctores(false);

    function cargarHorarios() {
        const doctorId = doctorSelect.value;
        const fecha = fechaInput.value;

        listaHorarios.innerHTML = "";
        horaInput.value = "";
        btnGuardar.disabled = true;

        if (doctorId === "" || fecha === "") {
            mensajeHorarios.textContent = "Selecciona primero un doctor y una fecha.";
            return;
        }

        mensajeHorarios.innerHTML = '<div class="cargando">Consultando horarios...</div>';

        fetch("horarios_disponibles?doctor_id=" + encodeURIComponent(doctorId) + "&fecha=" + encodeURIComponent(fecha))
            .then(function (response) { return response.json(); })
            .then(function (data) {
                listaHorarios.innerHTML = "";

                if (data.error) {
                    mensajeHorarios.textContent = data.error;
                    return;
                }

                if (!data.horarios || data.horarios.length === 0) {
                    mensajeHorarios.textContent = "No hay horarios disponibles para esta fecha.";
                    return;
                }

                mensajeHorarios.innerHTML = "<strong>Selecciona un horario libre:</strong>";

                data.horarios.forEach(function (horario) {
                    const textoHora = horario.substring(0, 5);
                    const boton = document.createElement("button");
                    boton.type = "button";
                    boton.className = "horario";
                    boton.textContent = textoHora;
                    boton.setAttribute("aria-pressed", "false");
                    boton.setAttribute("aria-label", "Seleccionar horario " + textoHora);

                    boton.addEventListener("click", function () {
                        document.querySelectorAll(".horario").forEach(function (elemento) {
                            elemento.classList.remove("seleccionado");
                            elemento.setAttribute("aria-pressed", "false");
                        });

                        boton.classList.add("seleccionado");
                        boton.setAttribute("aria-pressed", "true");
                        horaInput.value = horario;
                        btnGuardar.disabled = false;
                    });

                    listaHorarios.appendChild(boton);
                });
            })
            .catch(function (error) {
                console.error(error);
                mensajeHorarios.textContent = "Error al consultar los horarios.";
            });
    }
}
