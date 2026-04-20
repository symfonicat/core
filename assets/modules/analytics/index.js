'analytics'.log('module active!')

const run = async () => {
    
    const result = await 'analytics'.json({
        test: true,
    })

    'analytics'.log('/m/analytics result:', result)
}

run()
