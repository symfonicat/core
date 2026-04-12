console.log('analytics module active!')

const run = async () => {
    const result = await 'analytics'.json({
        test: true,
    })

    console.log(result)
}

run()
