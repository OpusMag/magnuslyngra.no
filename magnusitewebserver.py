import flask
import os
import socketserver

HOST = 'localhost'
PORT = 8080

app = flask.Flask(__name__)

@app.route('/')
def index():
    return flask.render_template('index.html')

@app.route('/about')

@app.route('/contact')

@app.route('/projects')

@app.route('/blog')

@app.route('/api')
def api():
    return flask.jsonify({
        'name': 'flask',
        'version': flask.__version__,
        'env': os.environ.get('FLASK_ENV', 'development'),
        'debug': app.debug,
        'hostname': socketserver.gethostname(),
    })
    
if __name__ == '__main__':
    app.run(HOST, PORT)