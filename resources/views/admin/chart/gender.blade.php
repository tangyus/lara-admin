<canvas id="doughnut" width="200" height="600"></canvas>
<script>
    $(function () {
        var config = {
            type: 'line',
            data: {
                datasets: [
                    {
                        label: 'PV',
                        backgroundColor: 'rgb(255, 99, 132)',
                        borderColor: 'rgb(255, 99, 132)',
                        data: [
                            @if(count($pvData) > 0)
                            @foreach($pvData as $datum)
                                {{ $datum }},
                            @endforeach
                            @endif
                        ],
                        fill: false,
                    },
                    {
                        label: 'UV',
                        backgroundColor: 'rgb(54, 162, 235)',
                        borderColor: 'rgb(54, 162, 235)',
                        data: [
                            @if(count($pvData) > 0)
                            @foreach($uvData as $datum)
                            {{ $datum }},
                            @endforeach
                            @endif
                        ],
                        fill: false,
                    }
                ],
                labels: [
                    @foreach($labels as $label)
                        '{{ $label }}',
                    @endforeach
                ]
            },
            options: {
                maintainAspectRatio: false,
                scales: {
                    xAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: '日期'
                        }
                    }],
                    yAxes: [{
                        display: true,
                        scaleLabel: {
                            display: true,
                            labelString: '值'
                        }
                    }]
                }
            }
        };

        var ctx = document.getElementById('doughnut').getContext('2d');
        new Chart(ctx, config);
    });
</script>